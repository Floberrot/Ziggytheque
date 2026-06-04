<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\Storage\ImageStorageInterface;

final class InMemoryImageStorage implements ImageStorageInterface
{
    /** @var array<string, string> key => binary */
    public array $stored = [];

    public function store(string $key, string $binary, string $contentType): string
    {
        $this->stored[$key] = $binary;

        return 'https://storage.test/' . $key;
    }
}
