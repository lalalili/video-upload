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
        'metadata_key' => 'target',
        'fields'       => [
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

    'routes' => [
        'enabled'       => true,
        'middleware'    => [],
        'prefix'        => 'video-upload',
        'webhook_path'  => 'webhooks/{provider}',
        'refresh_path'  => 'videos/{video}/refresh',
        'playback_path' => 'videos/{video}/playback',
    ],

    'webhooks' => [
        'cloudflare_stream' => [
            'secret' => env('CLOUDFLARE_STREAM_WEBHOOK_SECRET'),
        ],
    ],

    'playback' => [
        'signed' => [
            'enabled' => env('VIDEO_UPLOAD_SIGNED_PLAYBACK', false),
            'ttl'     => env('VIDEO_UPLOAD_SIGNED_PLAYBACK_TTL', 3600),
        ],
    ],

    'default_strategy' => env('VIDEO_UPLOAD_STRATEGY', 'provider_direct'),
];
