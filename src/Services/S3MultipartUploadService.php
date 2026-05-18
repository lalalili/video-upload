<?php

namespace Lalalili\VideoUpload\Services;

use Aws\S3\S3Client;
use Lalalili\VideoUpload\Models\VideoUploadSession;
use RuntimeException;

class S3MultipartUploadService
{
    /**
     * @return array{uploadId: string, key: string}
     */
    public function create(VideoUploadSession $session): array
    {
        if ($this->shouldFake($session)) {
            return [
                'uploadId' => 'test-upload-'.$session->ulid,
                'key' => (string) $session->staging_path,
            ];
        }

        $result = $this->client((string) $session->staging_disk)->createMultipartUpload([
            'Bucket' => $this->bucket((string) $session->staging_disk),
            'Key' => (string) $session->staging_path,
            'ContentType' => $session->mime_type ?: 'application/octet-stream',
            'Metadata' => [
                'video-id' => (string) $session->video_id,
                'upload-session-id' => (string) $session->id,
            ],
        ]);

        return [
            'uploadId' => (string) $result->get('UploadId'),
            'key' => (string) $session->staging_path,
        ];
    }

    /**
     * @return array{url: string, headers: array<string, string>}
     */
    public function signPart(VideoUploadSession $session, int $partNumber): array
    {
        if ($this->shouldFake($session)) {
            return [
                'url' => "https://s3-upload.test/{$session->staging_path}?partNumber={$partNumber}",
                'headers' => [],
            ];
        }

        $client = $this->client((string) $session->staging_disk);
        $command = $client->getCommand('UploadPart', [
            'Bucket' => $this->bucket((string) $session->staging_disk),
            'Key' => (string) $session->staging_path,
            'UploadId' => (string) $session->multipart_upload_id,
            'PartNumber' => $partNumber,
        ]);

        $request = $client->createPresignedRequest(
            $command,
            sprintf('+%d minutes', (int) config('video-upload.staging_temporary_url_minutes', 60))
        );

        return [
            'url' => (string) $request->getUri(),
            'headers' => [],
        ];
    }

    /**
     * @param  array<int, array{PartNumber:int, ETag:string}>  $parts
     * @return array{location: string|null}
     */
    public function complete(VideoUploadSession $session, array $parts): array
    {
        if ($this->shouldFake($session)) {
            return ['location' => "https://s3-upload.test/{$session->staging_path}"];
        }

        $result = $this->client((string) $session->staging_disk)->completeMultipartUpload([
            'Bucket' => $this->bucket((string) $session->staging_disk),
            'Key' => (string) $session->staging_path,
            'UploadId' => (string) $session->multipart_upload_id,
            'MultipartUpload' => ['Parts' => $parts],
        ]);

        return ['location' => $result->get('Location') ? (string) $result->get('Location') : null];
    }

    public function abort(VideoUploadSession $session): void
    {
        if ($this->shouldFake($session) || ! $session->multipart_upload_id) {
            return;
        }

        $this->client((string) $session->staging_disk)->abortMultipartUpload([
            'Bucket' => $this->bucket((string) $session->staging_disk),
            'Key' => (string) $session->staging_path,
            'UploadId' => (string) $session->multipart_upload_id,
        ]);
    }

    /**
     * @return array<int, array{PartNumber:int, ETag:string, Size:int}>
     */
    public function listParts(VideoUploadSession $session): array
    {
        if ($this->shouldFake($session) || ! $session->multipart_upload_id) {
            return [];
        }

        $result = $this->client((string) $session->staging_disk)->listParts([
            'Bucket' => $this->bucket((string) $session->staging_disk),
            'Key' => (string) $session->staging_path,
            'UploadId' => (string) $session->multipart_upload_id,
        ]);

        $parts = $result->get('Parts');

        return collect(is_array($parts) ? $parts : [])
            ->map(fn (array $part): array => [
                'PartNumber' => (int) $part['PartNumber'],
                'ETag' => (string) $part['ETag'],
                'Size' => (int) $part['Size'],
            ])
            ->all();
    }

    private function client(string $disk): S3Client
    {
        $config = $this->diskConfig($disk);

        return new S3Client(array_filter([
            'version' => 'latest',
            'region' => $config['region'] ?? config('aws.region', 'us-east-1'),
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'credentials' => [
                'key' => $config['key'] ?? config('aws.credentials.key'),
                'secret' => $config['secret'] ?? config('aws.credentials.secret'),
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function bucket(string $disk): string
    {
        $bucket = $this->diskConfig($disk)['bucket'] ?? null;

        if (! is_string($bucket) || $bucket === '') {
            throw new RuntimeException("S3 disk [{$disk}] does not define a bucket.");
        }

        return $bucket;
    }

    /**
     * @return array<string, mixed>
     */
    private function diskConfig(string $disk): array
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            throw new RuntimeException("Filesystem disk [{$disk}] is not configured.");
        }

        return $config;
    }

    private function shouldFake(VideoUploadSession $session): bool
    {
        $disk = (string) $session->staging_disk;

        if ($disk === '') {
            return true;
        }

        return app()->runningUnitTests()
            || ($this->diskConfig($disk)['driver'] ?? null) !== 's3';
    }
}
