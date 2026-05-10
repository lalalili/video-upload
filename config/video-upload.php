<?php

use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Models\VideoUploadSession;

return [
    'models' => [
        'video'   => Video::class,
        'session' => VideoUploadSession::class,
    ],

    'tables' => [
        'videos'   => 'videos',
        'sessions' => 'video_upload_sessions',
    ],

    'default_strategy' => env('VIDEO_UPLOAD_STRATEGY', 'provider_direct'),
];
