<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumePrices;

final readonly class GetVolumePricesQuery
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
        public ?string $marketplace = null,
    ) {
    }
}
