<?php

declare(strict_types=1);

namespace App\Manga\Application\UpdateVolume;

final readonly class UpdateVolumeCommand
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        public ?float $price = null,
    ) {
    }
}
