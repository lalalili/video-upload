<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
function makeS3User(): User
{
    $user = new User();
    $user->forceFill([
        'id'             => random_int(1, 999999),
        'name'           => 'S3 User',
        'email'          => 's3'.random_int(1, 9999).'@example.com',
        'is_super_admin' => false,
    ]);
    $user->save();

    return $user;
}

function makeS3Session(array $overrides = []): array
{
    $video = Video::create(['title' => 'S3 Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create(array_merge([
        'video_id'           => $video->id,
        'provider'           => 'cloudflare_stream',
        'strategy'           => 's3_multipart_then_import',
        'original_file_name' => 'upload.mp4',
        'file_size'          => 10 * 1024 * 1024,
        'bytes_uploaded'     => 0,
        'status'             => 'created',
        'staging_disk'       => '',
        'staging_path'       => 'uploads/test.mp4',
        'created_by'         => 1,
    ], $overrides));

    return [$video, $session];
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('create starts a multipart upload and sets uploading status', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session(['created_by' => $user->id]);

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.s3.multipart.create'), [
            'upload_session_id' => $session->id,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['uploadId', 'key', 'session'])
        ->assertJsonPath('session.status', 'uploading');

    expect($session->fresh()->status->value)->toBe('uploading');
});

it('create returns 422 when session is not in created state', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session(['created_by' => $user->id, 'status' => 'uploading']);

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.s3.multipart.create'), [
            'upload_session_id' => $session->id,
        ])
        ->assertUnprocessable();
});

it('create returns 403 when user does not own session', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session(['created_by' => $user->id + 1]);

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.s3.multipart.create'), [
            'upload_session_id' => $session->id,
        ])
        ->assertForbidden();
});

it('sign-part returns a presigned url', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session(['created_by' => $user->id, 'status' => 'uploading', 'multipart_upload_id' => 'mpid-1']);

    $response = $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.s3.multipart.sign-part', $session->id), [
            'partNumber' => 1,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['url']);
});

it('list-parts returns parts array', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session(['created_by' => $user->id, 'status' => 'uploading', 'multipart_upload_id' => 'mpid-1']);

    $response = $this->actingAs($user)
        ->getJson(route('video-upload.upload-center.s3.multipart.parts', $session->id));

    $response->assertOk()
        ->assertJsonStructure(['parts']);
});

it('abort cancels multipart and transitions session', function (): void {
    $user = makeS3User();
    [$video, $session] = makeS3Session(['created_by' => $user->id, 'status' => 'uploading', 'multipart_upload_id' => 'mpid-abort']);

    $this->actingAs($user)
        ->deleteJson(route('video-upload.upload-center.s3.multipart.abort', $session->id))
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');

    expect($session->fresh()->status->value)->toBe('cancelled')
        ->and($video->fresh()->provider_status)->toBe('cancelled');
});

it('complete finalises multipart and delegates to manager', function (): void {
    $user = makeS3User();
    [, $session] = makeS3Session([
        'created_by'          => $user->id,
        'status'              => 'uploading',
        'multipart_upload_id' => 'mpid-complete',
    ]);

    $called = false;
    $this->app->bind(VideoUploadSessionManagerContract::class, function () use (&$called): object {
        return new class($called) implements VideoUploadSessionManagerContract {
            public function __construct(private bool &$called) {}

            public function createUploadSession(Request $request): array
            {
                return ['upload_session' => new VideoUploadSession()];
            }

            public function completeUploadSession(Model $s): void
            {
                $this->called = true;
            }
        };
    });

    $this->actingAs($user)
        ->postJson(route('video-upload.upload-center.s3.multipart.complete', $session->id), [
            'parts' => [
                ['PartNumber' => 1, 'ETag' => 'etag-1'],
                ['PartNumber' => 2, 'ETag' => 'etag-2'],
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['location', 'session']);

    expect($called)->toBeTrue()
        ->and($session->fresh()->status->value)->toBe('uploaded');
});
