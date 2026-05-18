<?php

namespace Lalalili\VideoUpload\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Data\CourseVideoImportRequest;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;

class ImportStagedVideo implements ShouldQueue
{
    use Batchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $videoId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(CourseVideoPlatformManager $platformManager): void
    {
        $videoClass = config('video-upload.models.video', Video::class);
        $video = $videoClass::query()->find($this->videoId);

        if ($video === null) {
            return;
        }

        $stagingDisk = $video->staging_disk;
        $stagingPath = $video->staging_path;

        if (! is_string($stagingDisk) || $stagingDisk === '' || ! is_string($stagingPath) || $stagingPath === '') {
            return;
        }

        $video->update([
            'provider_status' => 'importing',
            'transcode_status' => 'importing',
        ]);

        $this->updateUploadSessions($video, ['status' => VideoUploadSessionStatus::Importing]);

        $sourceUrl = $this->sourceTemporaryUrl($stagingDisk, $stagingPath);

        $result = $platformManager
            ->provider($video->provider)
            ->importFromUrl(new CourseVideoImportRequest(
                sourceUrl: $sourceUrl,
                title: $video->title,
                description: $video->description,
                folderId: $video->folder_id,
                metadata: $video->metadata ?? [],
            ));

        $video->update([
            'provider_video_id' => $result->providerVideoId,
            'link' => $result->link,
            'player_embed_url' => $result->playerEmbedUrl,
            'duration' => $result->duration,
            'thumbnail_url' => $result->thumbnailUrl,
            'status' => $result->status,
            'transcode_status' => $result->transcodeStatus ?? 'in_progress',
            'provider_status' => 'imported',
            'metadata' => array_merge($video->metadata ?? [], $result->metadata),
        ]);

        $sessionStatus = $this->sessionStatusAfterImport($result->status, $result->transcodeStatus);

        $this->updateUploadSessions($video->refresh(), [
            'provider_video_id' => $result->providerVideoId,
            'status' => $sessionStatus,
            'failed_at' => null,
            'error_message' => null,
        ]);

        if ($sessionStatus === VideoUploadSessionStatus::Processing) {
            $jobClass = $this->refreshJobClass();
            $job = $jobClass::dispatch($this->videoId)
                ->delay(now()->addSeconds(30));

            if ($this->queueName()) {
                $job->onQueue((string) $this->queueName());
            }

            if ($this->queueConnection()) {
                $job->onConnection((string) $this->queueConnection());
            }
        }

        if ((bool) config('video-upload.cleanup_staging_after_import', true)) {
            Storage::disk($stagingDisk)->delete($stagingPath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $videoClass = config('video-upload.models.video', Video::class);
        $video = $videoClass::query()->find($this->videoId);

        if ($video === null) {
            return;
        }

        $video->update([
            'provider_status' => 'failed',
            'transcode_status' => 'failed',
            'metadata' => array_merge($video->metadata ?? [], [
                'last_import_error' => $exception->getMessage(),
            ]),
        ]);

        $this->updateUploadSessions($video, [
            'status' => VideoUploadSessionStatus::Failed,
            'failed_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);
    }

    /**
     * @return class-string<RefreshVideoProviderStatus>
     */
    protected function refreshJobClass(): string
    {
        return RefreshVideoProviderStatus::class;
    }

    protected function queueName(): ?string
    {
        return config('video-upload.queue.name') ?: null;
    }

    protected function queueConnection(): ?string
    {
        return config('video-upload.queue.connection') ?: null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateUploadSessions(Video $video, array $attributes): void
    {
        if (($attributes['status'] ?? null) instanceof VideoUploadSessionStatus) {
            $attributes['status'] = $attributes['status']->value;
        }

        $sessionClass = config('video-upload.models.session', VideoUploadSession::class);

        $sessionClass::query()
            ->where('video_id', $video->getKey())
            ->update($attributes);
    }

    private function sessionStatusAfterImport(int $videoStatus, ?string $transcodeStatus): VideoUploadSessionStatus
    {
        if ($videoStatus === 1 || in_array($transcodeStatus, ['ready', 'complete', 'completed', 'available'], true)) {
            return VideoUploadSessionStatus::Ready;
        }

        return VideoUploadSessionStatus::Processing;
    }

    private function sourceTemporaryUrl(string $disk, string $path): string
    {
        try {
            return Storage::disk($disk)->temporaryUrl(
                $path,
                now()->addMinutes((int) config('video-upload.staging_temporary_url_minutes', 60))
            );
        } catch (\Throwable $exception) {
            if (! app()->runningUnitTests()) {
                throw $exception;
            }

            return "https://temporary-download.test/{$path}";
        }
    }
}
