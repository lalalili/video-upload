<?php

$loader = require __DIR__.'/../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CourseCore\\', __DIR__.'/../../course-core/src/', true);
$loader->addPsr4('Lalalili\\VideoUpload\\', __DIR__.'/../src/', true);
$loader->addPsr4('Lalalili\\VideoUpload\\Tests\\', __DIR__.'/', true);
$loader->addClassMap([
    'Lalalili\\VideoUpload\\Tests\\TestCase' => __DIR__.'/TestCase.php',
    'Lalalili\\VideoUpload\\Tests\\UploadCenterTestCase' => __DIR__.'/UploadCenterTestCase.php',
]);
$loader->addClassMap([
    'Lalalili\\VideoUpload\\VideoUploadServiceProvider' => __DIR__.'/../src/VideoUploadServiceProvider.php',
    'Lalalili\\VideoUpload\\Contracts\\VideoUploadSessionManagerContract' => __DIR__.'/../src/Contracts/VideoUploadSessionManagerContract.php',
    'Lalalili\\VideoUpload\\Enums\\VideoUploadSessionStatus' => __DIR__.'/../src/Enums/VideoUploadSessionStatus.php',
    'Lalalili\\VideoUpload\\Http\\Controllers\\VideoUploadController' => __DIR__.'/../src/Http/Controllers/VideoUploadController.php',
    'Lalalili\\VideoUpload\\Http\\Controllers\\UploadCenter\\VideoUploadSessionController' => __DIR__.'/../src/Http/Controllers/UploadCenter/VideoUploadSessionController.php',
    'Lalalili\\VideoUpload\\Http\\Controllers\\UploadCenter\\S3MultipartUploadController' => __DIR__.'/../src/Http/Controllers/UploadCenter/S3MultipartUploadController.php',
    'Lalalili\\VideoUpload\\Jobs\\ImportStagedVideo' => __DIR__.'/../src/Jobs/ImportStagedVideo.php',
    'Lalalili\\VideoUpload\\Jobs\\RefreshVideoProviderStatus' => __DIR__.'/../src/Jobs/RefreshVideoProviderStatus.php',
    'Lalalili\\VideoUpload\\Models\\Video' => __DIR__.'/../src/Models/Video.php',
    'Lalalili\\VideoUpload\\Models\\VideoUploadSession' => __DIR__.'/../src/Models/VideoUploadSession.php',
    'Lalalili\\VideoUpload\\Services\\S3MultipartUploadService' => __DIR__.'/../src/Services/S3MultipartUploadService.php',
    'Lalalili\\VideoUpload\\Services\\VideoAutoSyncService' => __DIR__.'/../src/Services/VideoAutoSyncService.php',
    'Lalalili\\VideoUpload\\Services\\VideoPlaybackUrlService' => __DIR__.'/../src/Services/VideoPlaybackUrlService.php',
    'Lalalili\\VideoUpload\\Services\\VideoTargetSyncService' => __DIR__.'/../src/Services/VideoTargetSyncService.php',
    'Lalalili\\VideoUpload\\Services\\VideoUploadService' => __DIR__.'/../src/Services/VideoUploadService.php',
    'Lalalili\\VideoUpload\\Services\\VideoWebhookService' => __DIR__.'/../src/Services/VideoWebhookService.php',
    'Lalalili\\VideoUpload\\Support\\NullVideoUploadSessionManager' => __DIR__.'/../src/Support/NullVideoUploadSessionManager.php',
    'Lalalili\\VideoUpload\\Support\\VideoUploadSessionPayload' => __DIR__.'/../src/Support/VideoUploadSessionPayload.php',
]);

return $loader;
