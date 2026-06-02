<?php

declare(strict_types=1);

namespace App\Manga\Application\Scan;

final readonly class CreateScanSessionCommand
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
    ) {
    }
}
