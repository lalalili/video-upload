<?php

namespace Lalalili\VideoUpload\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('video-upload.tables.videos', 'videos');
    }

    public function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<VideoUploadSession, $this>
     */
    public function uploadSessions(): HasMany
    {
        $sessionModel = $this->sessionModelClass();

        /** @var HasMany<VideoUploadSession, $this> $relation */
        $relation = $this->hasMany($sessionModel);

        return $relation;
    }

    public function resolvedProviderVideoId(): ?string
    {
        return is_string($this->provider_video_id) && $this->provider_video_id !== ''
            ? $this->provider_video_id
            : null;
    }

    /**
     * @return class-string<VideoUploadSession>
     */
    protected function sessionModelClass(): string
    {
        $model = config('video-upload.models.session', VideoUploadSession::class);

        return is_string($model) && is_subclass_of($model, VideoUploadSession::class)
            ? $model
            : VideoUploadSession::class;
    }
}
