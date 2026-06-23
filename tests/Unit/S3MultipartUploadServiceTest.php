<?php

use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Services\S3MultipartUploadService;

beforeEach(function (): void {
    config()->set('filesystems.disks.s3-test', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'test-bucket',
    ]);

    config()->set('filesystems.disks.non-s3-test', [
        'driver' => 'local',
        'root' => '/tmp/test',
    ]);
});

it('fakes create when running in unit tests', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-test',
        'staging_path' => 'uploads/test.mp4',
    ]);

    $result = (new S3MultipartUploadService)->create($session);

    expect($result['uploadId'])->toStartWith('test-upload-')
        ->and($result['key'])->toBe('uploads/test.mp4');
});

it('fakes signPart when running in unit tests', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-test',
        'staging_path' => 'uploads/test.mp4',
        'multipart_upload_id' => 'mpu-123',
    ]);

    $result = (new S3MultipartUploadService)->signPart($session, 1);

    expect($result['url'])->toContain('partNumber=1')
        ->and($result['headers'])->toBeArray();
});

it('fakes complete when running in unit tests', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-test',
        'staging_path' => 'uploads/test.mp4',
    ]);

    $result = (new S3MultipartUploadService)->complete($session, [
        ['PartNumber' => 1, 'ETag' => 'etag-1'],
    ]);

    expect($result['location'])->toContain('uploads/test.mp4');
});

it('abort is a no-op without multipart_upload_id', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-test',
        'staging_path' => 'uploads/test.mp4',
    ]);

    // No exception = pass
    (new S3MultipartUploadService)->abort($session);

    expect(true)->toBeTrue();
});

it('listParts returns empty without multipart_upload_id', function (): void {
    $video = Video::create(['title' => 'Test', 'provider' => 'cloudflare_stream']);
    $session = VideoUploadSession::create([
        'video_id' => $video->id,
        'provider' => 'cloudflare_stream',
        'strategy' => 's3_multipart_then_import',
        'original_file_name' => 'test.mp4',
        'staging_disk' => 's3-test',
        'staging_path' => 'uploads/test.mp4',
    ]);

    expect((new S3MultipartUploadService)->listParts($session))->toBeEmpty();
});
