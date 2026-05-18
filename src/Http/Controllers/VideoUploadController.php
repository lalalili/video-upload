<?php

namespace Lalalili\VideoUpload\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lalalili\VideoUpload\Models\Video;
use Lalalili\VideoUpload\Services\VideoPlaybackUrlService;
use Lalalili\VideoUpload\Services\VideoUploadService;
use Lalalili\VideoUpload\Services\VideoWebhookService;

class VideoUploadController
{
    public function webhook(Request $request, string $provider, VideoWebhookService $webhooks): Response
    {
        abort_unless($this->validWebhookSecret($request, $provider), 403);

        $video = $webhooks->handle($provider, $request->all());

        return response($video instanceof Video ? '1|OK' : '0|NOT_FOUND', $video instanceof Video ? 200 : 404)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function refresh(int|string $video, VideoUploadService $uploads): JsonResponse
    {
        $video = $this->resolveVideo($video);
        $status = $uploads->refresh($video);

        return response()->json([
            'provider_video_id' => $status->providerVideoId,
            'ready' => $status->isReady,
            'status' => $status->status,
            'transcode_status' => $status->transcodeStatus,
        ]);
    }

    public function playback(Request $request, int|string $video, VideoPlaybackUrlService $playback): RedirectResponse
    {
        $video = $this->resolveVideo($video);

        if ($playback->signedPlaybackEnabled()) {
            abort_unless($request->hasValidSignature(), 403);
        }

        $url = $playback->targetUrl($video);
        abort_if(! is_string($url) || $url === '', 404);

        return redirect()->away($url);
    }

    private function resolveVideo(int|string $key): Video
    {
        $videoModel = $this->videoModelClass();

        $video = $videoModel::query()->whereKey($key)->firstOrFail();

        return $video;
    }

    private function validWebhookSecret(Request $request, string $provider): bool
    {
        $secret = config("video-upload.webhooks.{$provider}.secret");

        if (! is_string($secret) || $secret === '') {
            return true;
        }

        $providedSecret = $request->header('X-Video-Upload-Secret');
        if (! is_string($providedSecret)) {
            $providedSecret = $request->input('secret', '');
        }

        return hash_equals($secret, is_string($providedSecret) ? $providedSecret : '');
    }

    /**
     * @return class-string<Video>
     */
    private function videoModelClass(): string
    {
        $model = config('video-upload.models.video', Video::class);

        return is_string($model) && is_subclass_of($model, Video::class)
            ? $model
            : Video::class;
    }
}
