<?php

namespace Lalalili\VideoUpload\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;

class VideoUploadSessionPayload
{
    /**
     * @return array<string, mixed>
     */
    public function make(Model $session): array
    {
        $session->loadMissing('video:id,title,provider,provider_video_id,provider_status,transcode_status,player_embed_url,status');

        return [
            'id' => $session->getKey(),
            'ulid' => $session->ulid,
            'video_id' => $session->video_id,
            'folder_id' => $session->folder_id,
            'course_id' => $session->course_id ?? null,
            'course_chapter_id' => $session->course_chapter_id ?? null,
            'target_type' => $session->target_type ?? null,
            'target_id' => $session->target_id ?? null,
            'provider' => $session->provider,
            'strategy' => $session->strategy,
            'status' => $this->value($session->status),
            'name' => $session->original_file_name,
            'size' => $session->file_size,
            'type' => $session->mime_type,
            'title' => $session->title,
            'description' => $session->description,
            'upload_endpoint' => $session->upload_endpoint,
            'provider_video_id' => $session->provider_video_id,
            'staging_disk' => $session->staging_disk,
            'staging_path' => $session->staging_path,
            'bytes_uploaded' => $session->bytes_uploaded,
            'progress' => $this->progress($session),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'failed_at' => $session->failed_at?->toIso8601String(),
            'error_message' => $session->error_message,
            'metadata' => $session->metadata ?? [],
            'video' => $session->video !== null ? [
                'id' => $session->video->getKey(),
                'title' => $session->video->title,
                'provider' => $session->video->provider,
                'provider_status' => $session->video->provider_status,
                'transcode_status' => $session->video->transcode_status,
                'player_embed_url' => $session->video->player_embed_url,
                'provider_video_id' => $session->video->provider_video_id,
            ] : null,
        ];
    }

    private function progress(Model $session): int
    {
        if ((int) $session->file_size <= 0) {
            return 0;
        }

        return min(100, (int) floor(((int) $session->bytes_uploaded / (int) $session->file_size) * 100));
    }

    private function value(mixed $value): string
    {
        if ($value instanceof VideoUploadSessionStatus) {
            return $value->value;
        }

        if ($value instanceof BackedEnum && is_string($value->value)) {
            return $value->value;
        }

        return (string) $value;
    }
}
