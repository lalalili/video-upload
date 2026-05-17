<?php

use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;

it('allows cancel for in-progress statuses', function (): void {
    expect(VideoUploadSessionStatus::Created->canCancel())->toBeTrue()
        ->and(VideoUploadSessionStatus::Uploading->canCancel())->toBeTrue()
        ->and(VideoUploadSessionStatus::Uploaded->canCancel())->toBeTrue()
        ->and(VideoUploadSessionStatus::Importing->canCancel())->toBeTrue()
        ->and(VideoUploadSessionStatus::Processing->canCancel())->toBeTrue();
});

it('does not allow cancel for terminal statuses', function (): void {
    expect(VideoUploadSessionStatus::Ready->canCancel())->toBeFalse()
        ->and(VideoUploadSessionStatus::Failed->canCancel())->toBeFalse()
        ->and(VideoUploadSessionStatus::Cancelled->canCancel())->toBeFalse();
});

it('allows retry only for failed and cancelled', function (): void {
    expect(VideoUploadSessionStatus::Failed->canRetry())->toBeTrue()
        ->and(VideoUploadSessionStatus::Cancelled->canRetry())->toBeTrue()
        ->and(VideoUploadSessionStatus::Created->canRetry())->toBeFalse()
        ->and(VideoUploadSessionStatus::Ready->canRetry())->toBeFalse();
});

it('allows progress update only for created and uploading', function (): void {
    expect(VideoUploadSessionStatus::Created->canUpdateProgress())->toBeTrue()
        ->and(VideoUploadSessionStatus::Uploading->canUpdateProgress())->toBeTrue()
        ->and(VideoUploadSessionStatus::Uploaded->canUpdateProgress())->toBeFalse()
        ->and(VideoUploadSessionStatus::Ready->canUpdateProgress())->toBeFalse();
});
