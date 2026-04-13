<?php

declare(strict_types=1);

namespace App\Manga\Application\AddVolume;

final readonly class AddVolumeCommand
{
    public function __construct(
        public string $mangaId,
        public int $number,
        public ?string $coverUrl = null,
        public ?string $priceCode = null,
        public ?string $releaseDate = null,
    ) {
    }
}
