<?php

use Illuminate\Support\Facades\Http;
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use Lalalili\VideoUpload\Services\VideoPlaybackUrlService;
use Lalalili\VideoUpload\Services\VideoTargetSyncService;
use Lalalili\VideoUpload\Services\VideoUploadService;
use Lalalili\VideoUpload\Tests\Models\TestCourseUnit;

it('creates provider direct upload sessions through course-core platforms', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/direct_upload' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-1',
                'uploadURL' => 'https://upload.cloudflarestream.com/direct',
            ],
        ]),
    ]);

    $result = app(VideoUploadService::class)->createProviderDirectSession(
        new CourseVideoUploadRequest(
            fileName: 'intro.mp4',
            fileSize: 1024,
            mimeType: 'video/mp4',
            title: 'Course intro',
        ),
        context: [
            'source' => 'course_unit',
            'course_id' => 10,
            'course_chapter_id' => 20,
            'company_id' => 30,
            'created_by' => 40,
        ],
    );

    expect($result['video']->provider)->toBe('cloudflare_stream')
        ->and($result['video']->provider_video_id)->toBe('cloudflare-video-1')
        ->and($result['upload_session']->status)->toBe(VideoUploadSessionStatus::Created)
        ->and($result['upload_session']->upload_endpoint)->toBe('https://upload.cloudflarestream.com/direct')
        ->and($result['provider_session']->strategy)->toBe('provider_direct');
});

it('marks uploads complete and refreshes ready provider state', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/direct_upload' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-1',
                'uploadURL' => 'https://upload.cloudflarestream.com/direct',
            ],
        ]),
        'api.cloudflare.com/client/v4/accounts/account-123/stream/cloudflare-video-1' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-1',
                'readyToStream' => true,
                'duration' => 61000,
                'status' => ['state' => 'ready'],
            ],
        ]),
    ]);

    $service = app(VideoUploadService::class);
    $result = $service->createProviderDirectSession(new CourseVideoUploadRequest(
        fileName: 'intro.mp4',
        fileSize: 1024,
        mimeType: 'video/mp4',
    ));

    $session = $service->markUploaded($result['upload_session']);
    $status = $service->refresh($result['video']->refresh());

    expect($session->status)->toBe(VideoUploadSessionStatus::Processing)
        ->and($status->isReady)->toBeTrue()
        ->and($result['video']->refresh()->duration)->toBe(61)
        ->and(VideoUploadSession::query()->first()?->status)->toBe(VideoUploadSessionStatus::Ready);
});

it('syncs a target model automatically when refresh finds a ready video', function (): void {
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account-123/stream/direct_upload' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-2',
                'uploadURL' => 'https://upload.cloudflarestream.com/direct',
            ],
        ]),
        'api.cloudflare.com/client/v4/accounts/account-123/stream/cloudflare-video-2' => Http::response([
            'success' => true,
            'result' => [
                'uid' => 'cloudflare-video-2',
                'readyToStream' => true,
                'duration' => 120000,
                'status' => ['state' => 'ready'],
            ],
        ]),
    ]);

    $unit = TestCourseUnit::query()->create();
    $result = app(VideoUploadService::class)->createProviderDirectSession(
        new CourseVideoUploadRequest(
            fileName: 'lesson.mp4',
            fileSize: 1024,
            mimeType: 'video/mp4',
        ),
        context: [
            'target_model' => TestCourseUnit::class,
            'target_id' => $unit->getKey(),
        ],
    );

    app(VideoUploadService::class)->refresh($result['video']->refresh());

    expect($unit->refresh()->video_id)->toBe($result['video']->getKey())
        ->and($unit->provider_video_id)->toBe('cloudflare-video-2')
        ->and($unit->duration)->toBe(120)
        ->and($unit->provider_status)->toBe('ready');
});

it('handles cloudflare stream webhooks and syncs the configured target model', function (): void {
    $unit = TestCourseUnit::query()->create();
    $video = Video::query()->create([
        'provider' => 'cloudflare_stream',
        'provider_video_id' => 'cloudflare-video-3',
        'provider_status' => 'processing',
        'transcode_status' => 'processing',
        'title' => 'Course intro',
        'metadata' => [
            'target' => [
                'model' => TestCourseUnit::class,
                'id' => $unit->getKey(),
            ],
        ],
    ]);
    VideoUploadSession::query()->create([
        'video_id' => $video->getKey(),
        'provider' => 'cloudflare_stream',
        'strategy' => 'provider_direct',
        'status' => VideoUploadSessionStatus::Processing,
        'original_file_name' => 'intro.mp4',
        'provider_video_id' => 'cloudflare-video-3',
    ]);

    $this->post(route('video-upload.webhook', ['provider' => 'cloudflare_stream']), [
        'uid' => 'cloudflare-video-3',
        'readyToStream' => true,
        'duration' => 90000,
        'status' => ['state' => 'ready'],
    ])
        ->assertOk()
        ->assertContent('1|OK');

    expect($video->refresh()->provider_status)->toBe('ready')
        ->and(VideoUploadSession::query()->first()?->status)->toBe(VideoUploadSessionStatus::Ready)
        ->and($unit->refresh()->provider_video_id)->toBe('cloudflare-video-3')
        ->and($unit->video_url)->toBe('https://iframe.videodelivery.net/cloudflare-video-3');
});

it('syncs ready videos to configurable target model fields', function (): void {
    $video = Video::query()->create([
        'provider' => 'cloudflare_stream',
        'provider_video_id' => 'cloudflare-video-1',
        'provider_status' => 'ready',
        'transcode_status' => 'ready',
        'title' => 'Course intro',
        'player_embed_url' => 'https://iframe.videodelivery.net/cloudflare-video-1',
        'thumbnail_url' => 'https://image.example.com/thumb.jpg',
        'duration' => 61,
    ]);
    $unit = TestCourseUnit::query()->create();

    $syncedUnit = app(VideoTargetSyncService::class)->syncWhenReady($video, $unit);

    expect($syncedUnit)->toBeInstanceOf(TestCourseUnit::class)
        ->and($syncedUnit?->video_id)->toBe($video->getKey())
        ->and($syncedUnit?->provider)->toBe('cloudflare_stream')
        ->and($syncedUnit?->provider_video_id)->toBe('cloudflare-video-1')
        ->and($syncedUnit?->video_url)->toBe('https://iframe.videodelivery.net/cloudflare-video-1')
        ->and($syncedUnit?->thumbnail_url)->toBe('https://image.example.com/thumb.jpg')
        ->and($syncedUnit?->duration)->toBe(61);
});

it('does not sync processing videos when ready-only sync is requested', function (): void {
    $video = Video::query()->create([
        'provider' => 'cloudflare_stream',
        'provider_video_id' => 'cloudflare-video-1',
        'provider_status' => 'processing',
        'transcode_status' => 'in_progress',
        'title' => 'Course intro',
        'player_embed_url' => 'https://iframe.videodelivery.net/cloudflare-video-1',
    ]);
    $unit = TestCourseUnit::query()->create();

    expect(app(VideoTargetSyncService::class)->syncWhenReady($video, $unit))->toBeNull()
        ->and($unit->refresh()->video_url)->toBeNull();
});

it('generates signed package playback URLs when enabled', function (): void {
    config()->set('video-upload.playback.signed.enabled', true);
    $video = Video::query()->create([
        'provider' => 'cloudflare_stream',
        'provider_video_id' => 'cloudflare-video-4',
        'provider_status' => 'ready',
        'transcode_status' => 'ready',
        'title' => 'Course intro',
        'player_embed_url' => 'https://iframe.videodelivery.net/cloudflare-video-4',
    ]);

    $url = app(VideoPlaybackUrlService::class)->url($video);

    expect($url)->toContain('/video-upload/videos/'.$video->getKey().'/playback')
        ->and($url)->toContain('signature=');

    $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))
        ->assertRedirect('https://iframe.videodelivery.net/cloudflare-video-4');
});
