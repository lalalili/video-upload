<?php

namespace Lalalili\VideoUpload\Services;

use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\CourseCore\Data\CourseVideoStatus;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\CourseCore\Data\CourseVideoUploadSession as ProviderUploadSession;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;

class VideoUploadService
{
    public function __construct(
        private readonly CourseVideoPlatformManager $platformManager,
        private readonly VideoAutoSyncService $autoSync,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{video: Video, upload_session: VideoUploadSession, provider_session: ProviderUploadSession}
     */
    public function createProviderDirectSession(
        CourseVideoUploadRequest $request,
        ?string $provider = null,
        array $context = [],
    ): array {
        $provider ??= $this->platformManager->defaultProviderKey();
        $providerSession = $this->platformManager
            ->provider($provider)
            ->createDirectUploadSession($request);

        if (! $providerSession instanceof ProviderUploadSession) {
            throw new \RuntimeException("Video provider [{$provider}] does not support direct uploads.");
        }

        /** @var class-string<Video> $videoModel */
        $videoModel = config('video-upload.models.video', Video::class);
        /** @var class-string<VideoUploadSession> $sessionModel */
        $sessionModel = config('video-upload.models.session', VideoUploadSession::class);

        /** @var Video $video */
        $video = $videoModel::query()->create([
            'provider'          => $provider,
            'provider_video_id' => $providerSession->providerVideoId,
            'upload_strategy'   => $providerSession->strategy,
            'provider_status'   => 'pending_upload',
            'transcode_status'  => 'pending_upload',
            'metadata'          => $this->videoMetadata($request->metadata, $providerSession->metadata, $context),
            'title'             => $request->title ?? pathinfo($request->fileName, PATHINFO_FILENAME),
            'description'       => $request->description,
            'size'              => $request->fileSize,
            'mime_type'         => $request->mimeType,
            'folder_id'         => $request->folderId,
            'course_id'         => $context['course_id'] ?? null,
            'course_chapter_id' => $context['course_chapter_id'] ?? null,
            'company_id'        => $this->contextInt($context, 'company_id'),
            'created_by'        => $this->creatorId($request->creator, $context),
            'updated_by'        => $this->creatorId($request->creator, $context),
        ]);

        /** @var VideoUploadSession $session */
        $session = $sessionModel::query()->create([
            'video_id'           => $video->getKey(),
            'folder_id'          => $request->folderId,
            'course_id'          => $context['course_id'] ?? null,
            'course_chapter_id'  => $context['course_chapter_id'] ?? null,
            'company_id'         => $this->contextInt($context, 'company_id'),
            'created_by'         => $this->creatorId($request->creator, $context),
            'source'             => $context['source'] ?? null,
            'provider'           => $provider,
            'strategy'           => $providerSession->strategy,
            'status'             => VideoUploadSessionStatus::Created->value,
            'original_file_name' => $request->fileName,
            'file_size'          => $request->fileSize,
            'mime_type'          => $request->mimeType,
            'title'              => $request->title,
            'description'        => $request->description,
            'provider_video_id'  => $providerSession->providerVideoId,
            'upload_endpoint'    => $providerSession->uploadUrl,
            'upload_headers'     => $providerSession->headers,
            'metadata'           => $providerSession->metadata,
        ]);

        return [
            'video'            => $video,
            'upload_session'   => $session,
            'provider_session' => $providerSession,
        ];
    }

    public function markUploaded(VideoUploadSession $session): VideoUploadSession
    {
        $session->loadMissing('video');

        $session->video->update([
            'provider_status'  => 'uploaded',
            'transcode_status' => 'in_progress',
        ]);

        $session->update([
            'status'       => VideoUploadSessionStatus::Processing->value,
            'completed_at' => now(),
        ]);

        return $session->refresh();
    }

    public function refresh(Video $video): CourseVideoStatus
    {
        if (! $video->resolvedProviderVideoId()) {
            throw new \RuntimeException('Video is missing a provider video id.');
        }

        $status = $this->platformManager
            ->provider($video->provider)
            ->refreshStatus($video->resolvedProviderVideoId());

        $video->update([
            'provider_status'  => $status->status,
            'transcode_status' => $status->transcodeStatus,
            'duration'         => $status->duration,
            'thumbnail_url'    => $status->thumbnailUrl,
            'player_embed_url' => $status->playerEmbedUrl,
            'metadata'         => array_merge($video->metadata ?? [], $status->metadata),
        ]);

        $video->uploadSessions()
            ->whereIn('status', [
                VideoUploadSessionStatus::Created->value,
                VideoUploadSessionStatus::Uploading->value,
                VideoUploadSessionStatus::Uploaded->value,
                VideoUploadSessionStatus::Importing->value,
                VideoUploadSessionStatus::Processing->value,
            ])
            ->update(['status' => $status->isReady ? VideoUploadSessionStatus::Ready->value : VideoUploadSessionStatus::Processing->value]);

        if ($status->isReady) {
            $this->autoSync->syncWhenReady($video->refresh());
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $requestMetadata
     * @param  array<string, mixed>  $providerMetadata
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function videoMetadata(array $requestMetadata, array $providerMetadata, array $context): array
    {
        $metadata = array_merge($requestMetadata, $providerMetadata);
        $target = $this->autoSync->targetMetadataFromContext($context);

        if ($target !== []) {
            $metadata[(string) config('video-upload.target_sync.metadata_key', 'target')] = $target;
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function creatorId(?\Illuminate\Contracts\Auth\Authenticatable $creator, array $context): ?int
    {
        $id = $creator?->getAuthIdentifier() ?? $context['created_by'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextInt(array $context, string $key): ?int
    {
        return is_numeric($context[$key] ?? null) ? (int) $context[$key] : null;
    }
}
