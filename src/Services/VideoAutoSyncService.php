<?php

namespace Lalalili\VideoUpload\Services;

use Illuminate\Database\Eloquent\Model;

class VideoAutoSyncService
{
    public function __construct(private readonly VideoTargetSyncService $targets) {}

    public function syncWhenReady(Model $video): ?Model
    {
        $target = $this->resolveTarget($video);

        if (! $target instanceof Model) {
            return null;
        }

        return $this->targets->syncWhenReady($video, $target, $this->fieldMap($video));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{model?: class-string<Model>, id?: mixed, fields?: array<string, string>}
     */
    public function targetMetadataFromContext(array $context): array
    {
        $model = $context['target_model'] ?? $context['target_type'] ?? null;
        $id = $context['target_id'] ?? null;

        if (! is_string($model) || $model === '' || ! class_exists($model) || $id === null) {
            return [];
        }

        $metadata = [
            'model' => $model,
            'id' => $id,
        ];

        if (is_array($context['target_fields'] ?? null)) {
            /** @var array<string, string> $fields */
            $fields = array_filter($context['target_fields'], is_string(...));
            $metadata['fields'] = $fields;
        }

        return $metadata;
    }

    private function resolveTarget(Model $video): ?Model
    {
        $target = data_get($video->metadata ?? [], $this->metadataKey());
        $modelClass = data_get($target, 'model');
        $id = data_get($target, 'id');

        if (! is_string($modelClass) || $modelClass === '' || ! is_subclass_of($modelClass, Model::class) || $id === null) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $modelClass::query()->find($id);
    }

    /**
     * @return array<string, string>|null
     */
    private function fieldMap(Model $video): ?array
    {
        $fields = data_get($video->metadata ?? [], $this->metadataKey().'.fields');

        if (! is_array($fields)) {
            return null;
        }

        /** @var array<string, string> $fields */
        $fields = array_filter($fields, is_string(...));

        return $fields === [] ? null : $fields;
    }

    private function metadataKey(): string
    {
        $key = config('video-upload.target_sync.metadata_key', 'target');

        return is_string($key) && $key !== '' ? $key : 'target';
    }
}
