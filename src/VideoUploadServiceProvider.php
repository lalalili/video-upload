<?php

namespace Lalalili\VideoUpload;

use Lalalili\VideoUpload\Services\VideoUploadService;
use Lalalili\VideoUpload\Services\VideoTargetSyncService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VideoUploadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('video-upload')
            ->hasConfigFile('video-upload')
            ->hasMigrations([
                '2026_05_10_000001_create_video_upload_tables',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(VideoUploadService::class);
        $this->app->singleton(VideoTargetSyncService::class);
    }
}
