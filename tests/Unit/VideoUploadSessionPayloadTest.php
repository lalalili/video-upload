<?php

use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Support\VideoUploadSessionPayload;

it('builds payload with required keys', function (): void {
    $video = Video::create(['title' => 'Lesson 1', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'lesson.mp4',
        'file_size' => 2048,
        'bytes_uploaded' => 1024,
        'status' => 'uploading',
    ]);

    $payload = (new VideoUploadSessionPayload)->make($session);

    expect($payload['id'])->toBe($session->id)
        ->and($payload['ulid'])->toBe($session->ulid)
        ->and($payload['provider'])->toBe('cloudflare_stream')
        ->and($payload['status'])->toBe('uploading')
        ->and($payload['progress'])->toBe(50);
});

it('calculates zero progress when file_size is zero', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'test.mp4',
        'file_size' => 0,
    ]);

    $payload = (new VideoUploadSessionPayload)->make($session);

    expect($payload['progress'])->toBe(0);
});

it('includes target morph fields', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'test.mp4',
        'target_type' => 'App\\Models\\CourseUnit',
        'target_id' => 42,
    ]);

    $payload = (new VideoUploadSessionPayload)->make($session);

    expect($payload['target_type'])->toBe('App\\Models\\CourseUnit')
        ->and($payload['target_id'])->toBe(42);
});

it('includes video relation data when loaded', function (): void {
    $video = Video::create([
        'title' => 'Lesson Video',
        'provider' => 'cloudflare_stream',
        'provider_video_id' => 'cf-vid-1',
    ]);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'original_file_name' => 'test.mp4',
    ]);

    $payload = (new VideoUploadSessionPayload)->make($session);

    expect($payload['video'])->not->toBeNull()
        ->and($payload['video']['provider_video_id'])->toBe('cf-vid-1');
});
