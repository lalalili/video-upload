<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
function makeTestUser(bool $isSuperAdmin = false): User
{
    $user = new User();
    $user->forceFill([
        'id'             => random_int(1, 999999),
        'name'           => 'Test User',
        'email'          => 'test'.random_int(1, 9999).'@example.com',
        'is_super_admin' => $isSuperAdmin,
    ]);
    $user->save();

    return $user;
}

function makeVideoAndSession(array $sessionOverrides = []): array
{
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);

    $session = VideoUploadSession::create(array_merge([
        'video_id'           => $video->id,
        'provider'           => 'cloudflare_stream',
        'strategy'           => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'file_size'          => 1024 * 1024,
        'bytes_uploaded'     => 0,
        'status'             => 'created',
        'created_by'         => 1,
    ], $sessionOverrides));

    return [$video, $session];
}

it('index returns owned sessions for regular users', function (): void {
    $user = makeTestUser();
    [, $ownSession] = makeVideoAndSession(['created_by' => $user->id]);
    [, $otherSession] = makeVideoAndSession(['created_by' => $user->id + 1]);

    $response = $this->actingAs($user)
        ->getJson(route('video-upload.upload-center.videos.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownSession->id);
});

it('index returns all sessions for super admin', function (): void {
    $admin = makeTestUser(isSuperAdmin: true);
    makeVideoAndSession(['created_by' => 1]);
    makeVideoAndSession(['created_by' => 2]);

    $response = $this->actingAs($admin)
        ->getJson(route('video-upload.upload-center.videos.index'));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('store delegates to manager and returns 201', function (): void {
    $user = makeTestUser();
    $video = Video::create(['title' => 'Stored', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id'           => $video->id,
        'provider'           => 'cloudflare_stream',
        'strategy'           => 's3_multipart_then_import',
        'original_file_name' => 'stored.mp4',
        'file_size'          => 512,
        'created_by'         => $user->id,
        'status'             => 'created',
    ]);

    $this->app->bind(VideoUploadSessionManagerContract::class, function () use ($session): object {
        return new class($session) implements VideoUploadSessionManagerContract {
            public function __construct(private readonly VideoUploadSession $session) {}

            public function createUploadSession(Request $request): array
            {
                return ['upload_session' => $this->session];
            }

            public function completeUploadSession(Model $s): void {}
        };
    });

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.store'), ['name' => 'stored.mp4', 'size' => 512]);

    $response->assertCreated()
        ->assertJsonPath('name', 'stored.mp4');
});

it('progress updates bytes_uploaded and status', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'created', 'file_size' => 1024]);

    $response = $this->actingAs($user)
        ->patchJson(route('video-upload.upload-center.videos.progress', $session->id), [
            'bytes_uploaded' => 512,
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'uploading')
        ->assertJsonPath('progress', 50);

    expect($session->fresh()->bytes_uploaded)->toBe(512);
});

it('progress caps bytes_uploaded at file_size', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'created', 'file_size' => 1024]);

    $this->actingAs($user)
        ->patchJson(route('video-upload.upload-center.videos.progress', $session->id), [
            'bytes_uploaded' => 99999,
        ])
        ->assertOk();

    expect($session->fresh()->bytes_uploaded)->toBe(1024);
});

it('progress returns 403 when user does not own session', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id + 1]);

    $this->actingAs($user)
        ->patchJson(route('video-upload.upload-center.videos.progress', $session->id), ['bytes_uploaded' => 100])
        ->assertForbidden();
});

it('cancel transitions session and video to cancelled', function (): void {
    $user = makeTestUser();
    [$video, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'uploading']);

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.cancel', $session->id));

    $response->assertOk()->assertJsonPath('status', 'cancelled');

    expect($session->fresh()->status->value)->toBe('cancelled')
        ->and($video->fresh()->provider_status)->toBe('cancelled');
});

it('cancel returns 422 when session is already ready', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'ready']);

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.cancel', $session->id))
        ->assertUnprocessable();
});

it('fail marks session as failed with error message', function (): void {
    $user = makeTestUser();
    [$video, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'uploading']);

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.fail', $session->id), [
            'error_message' => 'Network error',
        ]);

    $response->assertOk()->assertJsonPath('status', 'failed');

    expect($session->fresh()->error_message)->toBe('Network error')
        ->and($video->fresh()->provider_status)->toBe('failed');
});

it('retry resets failed session back to created', function (): void {
    $user = makeTestUser();
    [$video, $session] = makeVideoAndSession([
        'created_by'     => $user->id,
        'status'         => 'failed',
        'bytes_uploaded' => 500,
        'error_message'  => 'old error',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.retry', $session->id));

    $response->assertOk()->assertJsonPath('status', 'created');

    $fresh = $session->fresh();
    expect($fresh->bytes_uploaded)->toBe(0)
        ->and($fresh->error_message)->toBeNull()
        ->and($video->fresh()->provider_status)->toBe('pending_upload');
});

it('complete delegates to manager', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'uploaded']);

    $called = false;
    $this->app->bind(VideoUploadSessionManagerContract::class, function () use (&$called): object {
        return new class($called) implements VideoUploadSessionManagerContract {
            public function __construct(private bool &$called) {}

            public function createUploadSession(Request $request): array
            {
                return ['upload_session' => new VideoUploadSession()];
            }

            public function completeUploadSession(Model $session): void
            {
                $this->called = true;
            }
        };
    });

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.complete', $session->id))
        ->assertOk();

    expect($called)->toBeTrue();
});

it('complete returns 422 for cancelled sessions', function (): void {
    $user = makeTestUser();
    [, $session] = makeVideoAndSession(['created_by' => $user->id, 'status' => 'cancelled']);

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.videos.complete', $session->id))
        ->assertUnprocessable();
});
