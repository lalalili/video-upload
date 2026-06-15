# Video Upload

Reusable Laravel video upload lifecycle package for course applications.

## Install

```bash
composer require lalalili/video-upload
php artisan vendor:publish --tag=video-upload-config
php artisan vendor:publish --tag=video-upload-migrations
php artisan migrate
```

For GitHub installs before a Packagist release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lalalili/course-core.git"},
        {"type": "vcs", "url": "https://github.com/lalalili/video-upload.git"}
    ]
}
```

## Environment

```dotenv
COURSE_VIDEO_PROVIDER=cloudflare_stream
CLOUDFLARE_STREAM_ACCOUNT_ID=
CLOUDFLARE_STREAM_API_TOKEN=
CLOUDFLARE_STREAM_WEBHOOK_SECRET=
VIDEO_UPLOAD_SIGNED_PLAYBACK=true
VIDEO_UPLOAD_SIGNED_PLAYBACK_TTL=3600
```

## Usage

Create a direct upload session and remember the host target model:

```php
use Lalalili\CourseCore\Data\CourseVideoUploadRequest;
use Lalalili\VideoUpload\Services\VideoUploadService;

$result = app(VideoUploadService::class)->createProviderDirectSession(
    new CourseVideoUploadRequest(
        fileName: 'lesson.mp4',
        fileSize: 1024,
        mimeType: 'video/mp4',
        title: 'Lesson 1',
    ),
    context: [
        'target_model' => App\Models\CourseUnit::class,
        'target_id' => $unit->id,
    ],
);
```

Cloudflare Stream webhooks can call:

```text
POST /video-upload/webhooks/cloudflare_stream
```

Send `X-Video-Upload-Secret` when `CLOUDFLARE_STREAM_WEBHOOK_SECRET` is configured.

Polling can call:

```text
POST /video-upload/videos/{video}/refresh
```

When a video becomes ready, `VideoTargetSyncService` copies provider fields to the configured target model.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
