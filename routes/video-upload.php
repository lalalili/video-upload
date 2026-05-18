<?php

use Illuminate\Support\Facades\Route;
use Lalalili\VideoUpload\Http\Controllers\UploadCenter\S3MultipartUploadController;
use Lalalili\VideoUpload\Http\Controllers\UploadCenter\VideoUploadSessionController;
use Lalalili\VideoUpload\Http\Controllers\VideoUploadController;

if (! config('video-upload.routes.enabled', true)) {
    return;
}

$router = Route::middleware(config('video-upload.routes.middleware', []));
$prefix = config('video-upload.routes.prefix', 'video-upload');

if (is_string($prefix) && $prefix !== '') {
    $router->prefix($prefix);
}

$router->group(function (): void {
    Route::post((string) config('video-upload.routes.webhook_path', 'webhooks/{provider}'), [VideoUploadController::class, 'webhook'])
        ->name('video-upload.webhook');

    Route::post((string) config('video-upload.routes.refresh_path', 'videos/{video}/refresh'), [VideoUploadController::class, 'refresh'])
        ->name('video-upload.refresh');

    Route::get((string) config('video-upload.routes.playback_path', 'videos/{video}/playback'), [VideoUploadController::class, 'playback'])
        ->name('video-upload.playback');
});

if (config('video-upload.upload_center.enabled', false)) {
    $routeName = (string) config('video-upload.upload_center.route_name', 'video-upload.upload-center');
    $ucMiddleware = config('video-upload.upload_center.middleware', ['auth', 'verified']);
    $ucPrefix = (string) config('video-upload.upload_center.prefix', 'upload-center');

    Route::middleware(is_array($ucMiddleware) ? $ucMiddleware : [$ucMiddleware])
        ->prefix($ucPrefix)
        ->name($routeName.'.')
        ->group(function (): void {
            Route::get('/videos', [VideoUploadSessionController::class, 'index'])
                ->name('videos.index');
            Route::post('/videos', [VideoUploadSessionController::class, 'store'])
                ->name('videos.store');
            Route::patch('/videos/{session}/progress', [VideoUploadSessionController::class, 'progress'])
                ->name('videos.progress');
            Route::post('/videos/{session}/complete', [VideoUploadSessionController::class, 'complete'])
                ->name('videos.complete');
            Route::post('/videos/{session}/cancel', [VideoUploadSessionController::class, 'cancel'])
                ->name('videos.cancel');
            Route::post('/videos/{session}/fail', [VideoUploadSessionController::class, 'fail'])
                ->name('videos.fail');
            Route::post('/videos/{session}/retry', [VideoUploadSessionController::class, 'retry'])
                ->name('videos.retry');

            Route::post('/s3/multipart', [S3MultipartUploadController::class, 'create'])
                ->name('s3.multipart.create');
            Route::get('/s3/multipart/{session}/parts', [S3MultipartUploadController::class, 'listParts'])
                ->name('s3.multipart.parts');
            Route::post('/s3/multipart/{session}/sign-part', [S3MultipartUploadController::class, 'signPart'])
                ->name('s3.multipart.sign-part');
            Route::post('/s3/multipart/{session}/complete', [S3MultipartUploadController::class, 'complete'])
                ->name('s3.multipart.complete');
            Route::delete('/s3/multipart/{session}', [S3MultipartUploadController::class, 'abort'])
                ->name('s3.multipart.abort');
        });
}
