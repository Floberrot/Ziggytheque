<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

final readonly class UpdateMangaRequest
{
    public function __construct(
        public ?string $title = null,
        public ?string $edition = null,
    ) {
    }
}
