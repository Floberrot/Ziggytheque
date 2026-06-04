<?php

declare(strict_types=1);

namespace App\Manga\Domain\Storage;

interface ImageStorageInterface
{
    /**
     * Persist a binary image under $key and return its public URL.
     */
    public function store(string $key, string $binary, string $contentType): string;
}
