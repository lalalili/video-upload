<?php

namespace Lalalili\VideoUpload\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;

class VideoWebhookService
{
    public function __construct(private readonly VideoAutoSyncService $autoSync) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $provider, array $payload): ?Model
    {
        $providerVideoId = $this->providerVideoId($payload);

        if ($providerVideoId === null) {
            return null;
        }

        $videoModel = $this->videoModelClass();

        $video = $videoModel::query()
            ->where('provider', $provider)
            ->where('provider_video_id', $providerVideoId)
            ->first();

        if (! $video instanceof Video) {
            return null;
        }

        $providerStatus = $this->status($payload);
        $ready = $this->isReady($payload, $providerStatus);

        $video->update([
            'provider_status' => $providerStatus,
            'transcode_status' => $providerStatus,
            'duration' => $this->secondsFromMilliseconds(data_get($payload, 'duration', data_get($payload, 'data.duration'))),
            'thumbnail_url' => $this->thumbnailUrl($provider, $providerVideoId, $payload),
            'player_embed_url' => $this->playerUrl($provider, $providerVideoId, $payload),
            'metadata' => array_merge($video->metadata ?? [], [
                'webhook' => [
                    'provider' => $provider,
                    'payload' => Arr::except($payload, ['secret']),
                    'received_at' => now()->toISOString(),
                ],
            ]),
        ]);

        $video->uploadSessions()
            ->whereIn('status', [
                VideoUploadSessionStatus::Created->value,
                VideoUploadSessionStatus::Uploading->value,
                VideoUploadSessionStatus::Uploaded->value,
                VideoUploadSessionStatus::Importing->value,
                VideoUploadSessionStatus::Processing->value,
            ])
            ->update(['status' => $ready ? VideoUploadSessionStatus::Ready->value : VideoUploadSessionStatus::Processing->value]);

        if ($ready) {
            $this->autoSync->syncWhenReady($video->refresh());
        }

        return $video->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerVideoId(array $payload): ?string
    {
        $id = data_get($payload, 'uid', data_get($payload, 'data.uid', data_get($payload, 'video.uid')));

        return is_string($id) && $id !== '' ? $id : null;
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
     * @param  array<string, mixed>  $payload
     */
    private function status(array $payload): string
    {
        $status = data_get($payload, 'status.state', data_get($payload, 'data.status.state', data_get($payload, 'state')));

        return is_string($status) && $status !== '' ? $status : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isReady(array $payload, string $status): bool
    {
        return (bool) data_get($payload, 'readyToStream', data_get($payload, 'data.readyToStream', false))
            || in_array($status, ['ready', 'complete', 'completed'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function thumbnailUrl(string $provider, string $providerVideoId, array $payload): ?string
    {
        $thumbnailUrl = data_get($payload, 'thumbnail', data_get($payload, 'data.thumbnail'));

        if (is_string($thumbnailUrl) && $thumbnailUrl !== '') {
            return $thumbnailUrl;
        }

        return $provider === 'cloudflare_stream'
            ? "https://videodelivery.net/{$providerVideoId}/thumbnails/thumbnail.jpg"
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function playerUrl(string $provider, string $providerVideoId, array $payload): ?string
    {
        $playerUrl = data_get($payload, 'player_embed_url', data_get($payload, 'data.player_embed_url'));

        if (is_string($playerUrl) && $playerUrl !== '') {
            return $playerUrl;
        }

        return $provider === 'cloudflare_stream'
            ? "https://iframe.videodelivery.net/{$providerVideoId}"
            : null;
    }

    private function secondsFromMilliseconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) ceil(((float) $value) / 1000);
    }
}
