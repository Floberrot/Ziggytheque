<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Storage;

use App\Manga\Domain\Storage\ImageStorageInterface;
use AsyncAws\S3\S3Client;

/**
 * Production adapter: stores volume-face photos in an S3-compatible bucket
 * (Railway object storage) and returns their public URL.
 */
final readonly class S3ImageStorage implements ImageStorageInterface
{
    public function __construct(
        private S3Client $client,
        private string $bucket,
        private string $publicBaseUrl,
    ) {
    }

    public function store(string $key, string $binary, string $contentType): string
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $binary,
            'ContentType' => $contentType,
        ])->resolve();

        return rtrim($this->publicBaseUrl, '/') . '/' . $key;
    }
}
