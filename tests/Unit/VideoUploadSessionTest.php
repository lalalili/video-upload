<?php

use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Tests\Models\TestCourseUnit;

it('auto-generates ulid on creation', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
    ]);

    expect($session->ulid)->not->toBeNull()
        ->and(strlen($session->ulid))->toBe(26);
});

it('stores staging fields', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-staging',
        'staging_path' => 'uploads/test.mp4',
        'multipart_upload_id' => 'mpu-123',
    ]);

    expect($session->staging_disk)->toBe('s3-staging')
        ->and($session->staging_path)->toBe('uploads/test.mp4')
        ->and($session->multipart_upload_id)->toBe('mpu-123');
});

it('casts status to enum', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'status' => 'uploading',
    ]);

    expect($session->status)->toBeInstanceOf(VideoUploadSessionStatus::class)
        ->and($session->status)->toBe(VideoUploadSessionStatus::Uploading);
});

it('can associate a generic target via morph', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $unit = TestCourseUnit::create([]);

    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'lesson.mp4',
        'target_type' => TestCourseUnit::class,
        'target_id' => $unit->id,
    ]);

    $loaded = VideoUploadSession::find($session->id);

    expect($loaded->target)->toBeInstanceOf(TestCourseUnit::class)
        ->and($loaded->target->id)->toBe($unit->id);
});

it('target is nullable when not set', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'lesson.mp4',
    ]);

    expect($session->target)->toBeNull();
});
