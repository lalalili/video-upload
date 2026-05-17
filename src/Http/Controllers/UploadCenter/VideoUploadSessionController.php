<?php

namespace Lalalili\VideoUpload\Http\Controllers\UploadCenter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Support\VideoUploadSessionPayload;

class VideoUploadSessionController
{
    public function __construct(private readonly VideoUploadSessionPayload $payload)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var class-string<Model> $sessionClass */
        $sessionClass = config('video-upload.models.session', VideoUploadSession::class);

        $query = $sessionClass::query()
            ->with('video:id,title,provider,provider_video_id,player_embed_url,status,transcode_status,provider_status');

        if (! $this->canViewAll($request)) {
            $query->where('created_by', $request->user()?->getAuthIdentifier());
        }

        $sessions = $query->latest()->limit(50)->get();

        return response()->json([
            'data' => $sessions->map(fn (Model $session): array => $this->payload->make($session))->all(),
        ]);
    }

    public function store(Request $request, VideoUploadSessionManagerContract $manager): JsonResponse
    {
        $result = $manager->createUploadSession($request);

        return response()->json($this->payload->make($result['upload_session']->refresh()), 201);
    }

    public function complete(Request $request, VideoUploadSessionManagerContract $manager): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_if($session->status->value === 'cancelled', 422, 'Cancelled upload sessions cannot be completed.');
        abort_if($session->status->value === 'failed', 422, 'Failed upload sessions must be retried before completing.');

        $manager->completeUploadSession($session);

        return response()->json($this->payload->make($session->refresh()));
    }

    public function progress(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_unless($session->status->canUpdateProgress(), 422, 'Progress cannot be updated for this upload session.');

        $validated = $request->validate([
            'bytes_uploaded' => ['required', 'integer', 'min:0'],
        ]);

        $bytesUploaded = min((int) $validated['bytes_uploaded'], (int) $session->file_size);

        $session->update([
            'status'         => 'uploading',
            'bytes_uploaded' => $bytesUploaded,
        ]);

        return response()->json($this->payload->make($session->refresh()));
    }

    public function cancel(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_unless($session->status->canCancel(), 422, 'This upload session cannot be cancelled.');

        $session->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
        ]);

        $session->video()->update([
            'provider_status'  => 'cancelled',
            'transcode_status' => 'cancelled',
        ]);

        return response()->json($this->payload->make($session->refresh()));
    }

    public function fail(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_if($session->status->value === 'ready', 422, 'Completed upload sessions cannot be marked as failed.');

        $validated = $request->validate([
            'error_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $session->update([
            'status'        => 'failed',
            'failed_at'     => now(),
            'error_message' => $validated['error_message'] ?? null,
        ]);

        $session->video()->update([
            'provider_status'  => 'failed',
            'transcode_status' => 'failed',
        ]);

        return response()->json($this->payload->make($session->refresh()));
    }

    public function retry(Request $request): JsonResponse
    {
        $session = $this->resolveSession($request);
        $this->authorizeSession($session);

        abort_unless($session->status->canRetry(), 422, 'This upload session cannot be retried.');

        $session->update([
            'status'              => 'created',
            'bytes_uploaded'      => 0,
            'multipart_upload_id' => null,
            'failed_at'           => null,
            'completed_at'        => null,
            'error_message'       => null,
        ]);

        $session->video()->update([
            'provider_status'  => 'pending_upload',
            'transcode_status' => 'pending_upload',
        ]);

        return response()->json($this->payload->make($session->refresh()));
    }

    protected function canViewAll(Request $request): bool
    {
        return (bool) $request->user()?->is_super_admin;
    }

    protected function authorizeSession(Model $session): void
    {
        abort_unless(
            (int) $session->created_by === (int) auth()->id() || (bool) auth()->user()?->is_super_admin,
            403
        );
    }

    protected function resolveSession(Request $request, string $param = 'session'): Model
    {
        /** @var class-string<Model> $sessionClass */
        $sessionClass = config('video-upload.models.session', VideoUploadSession::class);

        return $sessionClass::query()->findOrFail($request->route($param));
    }
}
