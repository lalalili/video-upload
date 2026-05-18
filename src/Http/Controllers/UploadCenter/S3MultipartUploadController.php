<?php

namespace Lalalili\VideoUpload\Http\Controllers\UploadCenter;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Services\S3MultipartUploadService;
use Lalalili\VideoUpload\Support\VideoUploadSessionPayload;

class S3MultipartUploadController
{
    public function __construct(private readonly VideoUploadSessionPayload $payload) {}

    public function create(Request $request, S3MultipartUploadService $multipart): JsonResponse
    {
        $sessionTable = config('video-upload.tables.sessions', 'video_upload_sessions');

        $validated = $request->validate([
            'upload_session_id' => ['required', 'integer', "exists:{$sessionTable},id"],
        ]);

        $session = $this->resolveSessionById((int) $validated['upload_session_id']);
        $this->authorizeSession($session);

        abort_unless($session->status->value === 'created', 422, 'Multipart upload can only be started for sessions in the created state.');

        $created = $multipart->create($session);

        $session->update([
            'multipart_upload_id' => $created['uploadId'],
            'status' => 'uploading',
        ]);

        return response()->json([
            'uploadId' => $created['uploadId'],
            'key' => $created['key'],
            'session' => $this->payload->make($session->refresh()),
        ]);
    }

    public function signPart(Request $request, S3MultipartUploadService $multipart): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        $validated = $request->validate([
            'partNumber' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        return response()->json($multipart->signPart($session, (int) $validated['partNumber']));
    }

    public function listParts(Request $request, S3MultipartUploadService $multipart): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        return response()->json(['parts' => $multipart->listParts($session)]);
    }

    public function complete(
        Request $request,
        S3MultipartUploadService $multipart,
        VideoUploadSessionManagerContract $manager,
    ): JsonResponse {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        $validated = $request->validate([
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.PartNumber' => ['required_without:parts.*.partNumber', 'integer', 'min:1', 'max:10000'],
            'parts.*.partNumber' => ['required_without:parts.*.PartNumber', 'integer', 'min:1', 'max:10000'],
            'parts.*.ETag' => ['required_without:parts.*.etag', 'string'],
            'parts.*.etag' => ['required_without:parts.*.ETag', 'string'],
        ]);

        $validatedParts = is_array($validated['parts'] ?? null) ? $validated['parts'] : [];
        $parts = collect($validatedParts)
            ->map(fn (array $part): array => [
                'PartNumber' => (int) ($part['PartNumber'] ?? $part['partNumber']),
                'ETag' => (string) ($part['ETag'] ?? $part['etag']),
            ])
            ->sortBy('PartNumber')
            ->values()
            ->all();

        $result = $multipart->complete($session, $parts);

        $session->update([
            'status' => 'uploaded',
            'completed_at' => now(),
            'metadata' => array_merge($session->metadata ?? [], ['s3_complete' => $result]),
            'bytes_uploaded' => (int) $session->file_size,
        ]);

        $manager->completeUploadSession($session->refresh());

        return response()->json([
            'location' => $result['location'],
            'session' => $this->payload->make($session->refresh()),
        ]);
    }

    public function abort(Request $request, S3MultipartUploadService $multipart): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_unless($session->status->canCancel(), 422, 'This upload session cannot be cancelled.');

        $multipart->abort($session);

        $session->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        $session->video()->update([
            'provider_status' => 'cancelled',
            'transcode_status' => 'cancelled',
        ]);

        return response()->json($this->payload->make($session->refresh()));
    }

    protected function authorizeSession(VideoUploadSession $session): void
    {
        abort_unless(
            (int) $session->created_by === (int) auth()->id() || (bool) auth()->user()?->is_super_admin,
            403
        );
    }

    protected function resolveSession(Request $request, string $param = 'session'): VideoUploadSession
    {
        $sessionClass = $this->sessionModelClass();

        return $sessionClass::query()->whereKey($request->route($param))->firstOrFail();
    }

    protected function resolveSessionById(int $id): VideoUploadSession
    {
        $sessionClass = $this->sessionModelClass();

        return $sessionClass::query()->whereKey($id)->firstOrFail();
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
