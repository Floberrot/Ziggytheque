<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class ExternalVolumeDto
{
    public function __construct(
        public int $number,
        public ?string $coverUrl,
        public ?\DateTimeImmutable $releaseDate,
    ) {
    }
}
