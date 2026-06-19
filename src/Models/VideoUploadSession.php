<?php

namespace Lalalili\VideoUpload\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;

/**
 * @property VideoUploadSessionStatus $status
 * @property array<string, mixed>|null $metadata
 */
class VideoUploadSession extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('video-upload.tables.sessions', 'video_upload_sessions');
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function casts(): array
    {
        return [
            'status' => VideoUploadSessionStatus::class,
            'upload_headers' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Generic owner of this upload session (e.g. a course unit).
     * Host sets target_type/target_id when creating the session.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        $videoModel = $this->videoModelClass();

        /** @var BelongsTo<Video, $this> $relation */
        $relation = $this->belongsTo($videoModel);

        return $relation;
    }

    /**
     * @return class-string<Video>
     */
    protected function videoModelClass(): string
    {
        $model = config('video-upload.models.video', Video::class);

        return is_string($model) && is_subclass_of($model, Video::class)
            ? $model
            : Video::class;
    }
}
