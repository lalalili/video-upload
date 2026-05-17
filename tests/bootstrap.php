<?php

$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CourseCore\\', __DIR__.'/../../course-core/src/', true);
$loader->addPsr4('Lalalili\\VideoUpload\\', __DIR__.'/../src/', true);
$loader->addPsr4('Lalalili\\VideoUpload\\Tests\\', __DIR__.'/', true);
$loader->addClassMap([
    'Lalalili\\VideoUpload\\VideoUploadServiceProvider' => __DIR__.'/../src/VideoUploadServiceProvider.php',
    'Lalalili\\VideoUpload\\Enums\\VideoUploadSessionStatus' => __DIR__.'/../src/Enums/VideoUploadSessionStatus.php',
    'Lalalili\\VideoUpload\\Http\\Controllers\\VideoUploadController' => __DIR__.'/../src/Http/Controllers/VideoUploadController.php',
    'Lalalili\\VideoUpload\\Models\\Video' => __DIR__.'/../src/Models/Video.php',
    'Lalalili\\VideoUpload\\Models\\VideoUploadSession' => __DIR__.'/../src/Models/VideoUploadSession.php',
    'Lalalili\\VideoUpload\\Services\\VideoAutoSyncService' => __DIR__.'/../src/Services/VideoAutoSyncService.php',
    'Lalalili\\VideoUpload\\Services\\VideoPlaybackUrlService' => __DIR__.'/../src/Services/VideoPlaybackUrlService.php',
    'Lalalili\\VideoUpload\\Services\\VideoTargetSyncService' => __DIR__.'/../src/Services/VideoTargetSyncService.php',
    'Lalalili\\VideoUpload\\Services\\VideoUploadService' => __DIR__.'/../src/Services/VideoUploadService.php',
    'Lalalili\\VideoUpload\\Services\\VideoWebhookService' => __DIR__.'/../src/Services/VideoWebhookService.php',
]);

return $loader;
