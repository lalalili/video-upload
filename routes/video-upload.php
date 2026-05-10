<?php

use Illuminate\Support\Facades\Route;
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
