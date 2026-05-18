<?php

namespace Lalalili\VideoUpload\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\VideoUpload\Models\Video;

class VideoTargetSyncService
{
    /**
     * @var array<string, string>
     */
    private const DEFAULT_FIELD_MAP = [
        'video_id' => 'id',
        'provider' => 'provider',
        'provider_video_id' => 'provider_video_id',
        'video_url' => 'player_embed_url',
        'thumbnail_url' => 'thumbnail_url',
        'duration' => 'duration',
        'provider_status' => 'provider_status',
        'transcode_status' => 'transcode_status',
    ];

    /**
     * @param  array<string, string>|null  $fieldMap
     */
    public function sync(Video $video, Model $target, ?array $fieldMap = null): Model
    {
        $attributes = $this->mappedAttributes($video, $target, $fieldMap ?? $this->configuredFieldMap());

        if ($attributes === []) {
            return $target;
        }

        $target->forceFill($attributes)->save();

        return $target->refresh();
    }

    /**
     * @param  array<string, string>|null  $fieldMap
     */
    public function syncWhenReady(Video $video, Model $target, ?array $fieldMap = null): ?Model
    {
        if (! $this->isReady($video)) {
            return null;
        }

        return $this->sync($video, $target, $fieldMap);
    }

    /**
     * @param  array<string, string>  $fieldMap
     * @return array<string, mixed>
     */
    private function mappedAttributes(Video $video, Model $target, array $fieldMap): array
    {
        $attributes = [];

        foreach ($fieldMap as $targetField => $videoField) {
            if (! $target->getConnection()->getSchemaBuilder()->hasColumn($target->getTable(), $targetField)) {
                continue;
            }

            $attributes[$targetField] = data_get($video, $videoField);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function configuredFieldMap(): array
    {
        $fieldMap = config('video-upload.target_sync.fields', []);

        if (! is_array($fieldMap)) {
            return self::DEFAULT_FIELD_MAP;
        }

        return $fieldMap === [] ? self::DEFAULT_FIELD_MAP : $fieldMap;
    }

    private function isReady(Video $video): bool
    {
        return in_array((string) $video->provider_status, ['ready', 'complete', 'completed'], true)
            || in_array((string) $video->transcode_status, ['ready', 'complete', 'completed'], true);
    }
}
