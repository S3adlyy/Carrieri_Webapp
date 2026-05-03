<?php
declare(strict_types=1);

namespace App\Service;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class S3Service
{
    private S3Client $client;

    public function __construct(
        private readonly string $region,
        private readonly string $bucket
    ) {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
        ]);
    }

    public function uploadFile(UploadedFile $file, string $key, ?string $contentType = null): string
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SourceFile' => $file->getRealPath(),
            'ContentType' => $contentType ?? ($file->getMimeType() ?: 'application/octet-stream'),
        ]);

        return $key;
    }

    public function uploadBody(string $key, string $body, string $contentType = 'text/plain'): string
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $body,
            'ContentType' => $contentType,
        ]);

        return $key;
    }

    public function downloadBytes(string $key): string
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        return (string) $result['Body'];
    }

    public function downloadToTempFile(string $key): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'carrieri_s3_');

        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SaveAs' => $tmp,
        ]);

        return $tmp;
    }

    public function presignedDownloadUrl(string $key, int $expirySeconds = 600): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$expirySeconds} seconds");
        return (string) $request->getUri();
    }

    public function presignedUploadUrl(string $key, string $contentType, int $expirySeconds = 300): string
    {
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$expirySeconds} seconds");
        return (string) $request->getUri();
    }

    public function exists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (S3Exception) {
            return false;
        }
    }

    public function delete(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    public function getRegion(): string
    {
        return $this->region;
    }
}