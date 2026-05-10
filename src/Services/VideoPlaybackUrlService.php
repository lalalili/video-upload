<?php

namespace Lalalili\VideoUpload\Services;

use Illuminate\Support\Facades\URL;
use Lalalili\VideoUpload\Models\Video;

class VideoPlaybackUrlService
{
    public function url(Video $video): ?string
    {
        if ($this->signedPlaybackEnabled()) {
            return URL::temporarySignedRoute(
                'video-upload.playback',
                now()->addSeconds($this->ttl()),
                ['video' => $video->getKey()],
            );
        }

        return $video->player_embed_url;
    }

    public function targetUrl(Video $video): ?string
    {
        return $video->player_embed_url;
    }

    public function signedPlaybackEnabled(): bool
    {
        return (bool) config('video-upload.playback.signed.enabled', false);
    }

    private function ttl(): int
    {
        $ttl = config('video-upload.playback.signed.ttl', 3600);

        return is_numeric($ttl) ? max(60, (int) $ttl) : 3600;
    }
}
