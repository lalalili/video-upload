<?php

use Lalalili\CourseCore\Contracts\CourseVideoPlatformManager;
use Lalalili\VideoUpload\Jobs\RefreshVideoProviderStatus;

it('handles legacy queued payloads without initialized polling properties', function (): void {
    $serialized = sprintf('O:%d:"%s":0:{}', strlen(RefreshVideoProviderStatus::class), RefreshVideoProviderStatus::class);
    $job = unserialize($serialized);

    expect($job)->toBeInstanceOf(RefreshVideoProviderStatus::class)
        ->and($job->videoId)->toBeNull()
        ->and($job->attempt)->toBe(0);

    $job->handle(app(CourseVideoPlatformManager::class));
});
