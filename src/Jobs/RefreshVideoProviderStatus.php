<?php

namespace Lalalili\VideoUpload\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Services\VideoAutoSyncService;

class RefreshVideoProviderStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const WATCHABLE_TRANSCODE_STATUSES = ['in_progress', 'uploading', 'queued', 'imported', 'inprogress'];

    private const MAX_SINGLE_VIDEO_ATTEMPTS = 5;

    private const SINGLE_VIDEO_BACKOFF_SECONDS = [60, 120, 300, 300, 300];

    public function __construct(
        public readonly ?int $videoId = null,
        public readonly int $attempt = 0,
    ) {}

    public function handle(CourseVideoPlatformManager $platforms): void
    {
        $videoClass = $this->videoModelClass();

        if ($this->videoId !== null) {
            $video = $videoClass::query()->find($this->videoId);

            if ($video !== null) {
                $this->syncVideo($video, $platforms);
                $this->rescheduleIfNeeded($video->refresh());
            }

            return;
        }

        $videoClass::query()
            ->where('status', 0)
            ->where(function ($query): void {
                $query->whereIn('transcode_status', self::WATCHABLE_TRANSCODE_STATUSES)
                    ->orWhereNull('transcode_status');
            })
            ->orderBy('id')
            ->cursor()
            ->each(function ($video) use ($platforms): void {
                $this->syncVideo($video, $platforms);
            });
    }

    protected function queueName(): ?string
    {
        return config('video-upload.queue.name') ?: null;
    }

    protected function queueConnection(): ?string
    {
        return config('video-upload.queue.connection') ?: null;
    }

    private function rescheduleIfNeeded(Video $video): void
    {
        if ($video->status === 1) {
            return;
        }

        $transcode = $video->transcode_status;

        if (is_string($transcode) && ! in_array($transcode, self::WATCHABLE_TRANSCODE_STATUSES, true)) {
            return;
        }

        if ($this->attempt >= self::MAX_SINGLE_VIDEO_ATTEMPTS) {
            Log::warning('[RefreshVideoProviderStatus] max single-video polling attempts reached, stopping', [
                'video_id' => $video->id,
                'attempt' => $this->attempt,
            ]);

            return;
        }

        $delay = self::SINGLE_VIDEO_BACKOFF_SECONDS[$this->attempt] ?? 300;

        $job = static::dispatch($this->videoId, $this->attempt + 1)
            ->delay(now()->addSeconds($delay));

        if ($this->queueName()) {
            $job->onQueue((string) $this->queueName());
        }

        if ($this->queueConnection()) {
            $job->onConnection((string) $this->queueConnection());
        }
    }

    private function syncVideo(Video $video, CourseVideoPlatformManager $platforms): void
    {
        try {
            $providerVideoId = $video->provider_video_id;

            if (! $providerVideoId) {
                $video->update([
                    'provider_status' => 'missing_provider_video_id',
                    'transcode_status' => 'missing_provider_video_id',
                ]);

                $this->syncUploadSessions($video, VideoUploadSessionStatus::Failed, [
                    'error_message' => 'missing_provider_video_id',
                    'failed_at' => now(),
                ]);

                Log::warning('[RefreshVideoProviderStatus] provider video ID missing, stopping', [
                    'video_id' => $video->id,
                    'provider' => $video->provider,
                ]);

                return;
            }

            $status = $platforms
                ->provider($video->provider)
                ->refreshStatus($providerVideoId);

            if ($status->isReady) {
                $video->update([
                    'duration' => $status->duration ?? $video->duration,
                    'thumbnail_url' => $status->thumbnailUrl ?? $video->thumbnail_url,
                    'player_embed_url' => $status->playerEmbedUrl ?? $video->player_embed_url,
                    'status' => 1,
                    'provider_status' => $status->status,
                    'transcode_status' => $status->transcodeStatus ?? 'completed',
                    'metadata' => array_merge($this->metadata($video), $status->metadata),
                ]);

                $this->syncUploadSessions($video, VideoUploadSessionStatus::Ready);
                app(VideoAutoSyncService::class)->syncWhenReady($video->refresh());

                return;
            }

            $video->update([
                'provider_status' => $status->status,
                'transcode_status' => $status->transcodeStatus ?? $status->status,
                'metadata' => array_merge($this->metadata($video), $status->metadata),
            ]);

            $this->syncUploadSessions($video, VideoUploadSessionStatus::Processing);
        } catch (\Throwable $exception) {
            Log::error('RefreshVideoProviderStatus: failed to sync video provider status', [
                'video_id' => $video->id,
                'provider' => $video->provider,
                'provider_video_id' => $video->provider_video_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncUploadSessions(Video $video, VideoUploadSessionStatus $status, array $attributes = []): void
    {
        $sessionClass = $this->sessionModelClass();

        $sessionClass::query()
            ->where('video_id', $video->getKey())
            ->whereIn('status', [
                VideoUploadSessionStatus::Importing->value,
                VideoUploadSessionStatus::Processing->value,
            ])
            ->update(array_merge(['status' => $status->value], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(Video $video): array
    {
        return is_array($video->metadata) ? $video->metadata : [];
    }

    /**
     * @return class-string<Video>
     */
    private function videoModelClass(): string
    {
        $model = config('video-upload.models.video', Video::class);

        return is_string($model) && is_subclass_of($model, Video::class)
            ? $model
            : Video::class;
    }

    /**
     * @return class-string<VideoUploadSession>
     */
    private function sessionModelClass(): string
    {
        $model = config('video-upload.models.session', VideoUploadSession::class);

        return is_string($model) && is_subclass_of($model, VideoUploadSession::class)
            ? $model
            : VideoUploadSession::class;
    }
}
