<?php

declare(strict_types=1);

namespace App\Manga\Domain;

use DateTimeImmutable;

final readonly class ExternalVolumeDto
{
    public function __construct(
        public int $number,
        public ?string $coverUrl,
        public ?DateTimeImmutable $releaseDate,
    ) {
    }
}
