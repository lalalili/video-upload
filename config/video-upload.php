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

    'target_sync' => [
        'fields' => [
            'video_id'          => 'id',
            'provider'          => 'provider',
            'provider_video_id' => 'provider_video_id',
            'video_url'         => 'player_embed_url',
            'thumbnail_url'     => 'thumbnail_url',
            'duration'          => 'duration',
            'provider_status'   => 'provider_status',
            'transcode_status'  => 'transcode_status',
        ],
    ],

    'default_strategy' => env('VIDEO_UPLOAD_STRATEGY', 'provider_direct'),
];
