<?php

namespace Lalalili\VideoUpload;

use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;
use Lalalili\VideoUpload\Services\VideoAutoSyncService;
use Lalalili\VideoUpload\Services\VideoPlaybackUrlService;
use Lalalili\VideoUpload\Services\VideoTargetSyncService;
use Lalalili\VideoUpload\Services\VideoUploadService;
use Lalalili\VideoUpload\Services\VideoWebhookService;
use Lalalili\VideoUpload\Support\NullVideoUploadSessionManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VideoUploadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('video-upload')
            ->hasConfigFile('video-upload')
            ->hasRoute('video-upload')
            ->hasMigrations([
                '2026_05_10_000001_create_video_upload_tables',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(VideoUploadService::class);
        $this->app->singleton(VideoAutoSyncService::class);
        $this->app->singleton(VideoPlaybackUrlService::class);
        $this->app->singleton(VideoTargetSyncService::class);
        $this->app->singleton(VideoWebhookService::class);
        $this->app->bindIf(VideoUploadSessionManagerContract::class, NullVideoUploadSessionManager::class);
    }
}
