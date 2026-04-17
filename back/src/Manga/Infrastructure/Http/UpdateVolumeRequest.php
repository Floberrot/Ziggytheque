<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

final readonly class UpdateVolumeRequest
{
    public function __construct(
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        public ?string $priceCode = null,
    ) {
    }
}
