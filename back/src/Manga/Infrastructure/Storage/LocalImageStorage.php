<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Storage;

use App\Manga\Domain\Storage\ImageStorageInterface;
use RuntimeException;

/**
 * Dev / single-host adapter: writes under public/ and returns a path-based URL.
 * Production uses S3ImageStorage (Railway bucket), wired via when@prod in services.yaml.
 */
final readonly class LocalImageStorage implements ImageStorageInterface
{
    public function __construct(
        private string $uploadsDir,
        private string $publicBaseUrl,
    ) {
    }

    public function store(string $key, string $binary, string $contentType): string
    {
        $path = $this->uploadsDir . '/' . $key;
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create upload directory: ' . $directory);
        }

        if (file_put_contents($path, $binary) === false) {
            throw new RuntimeException('Unable to write upload: ' . $path);
        }

        return rtrim($this->publicBaseUrl, '/') . '/' . $key;
    }
}
