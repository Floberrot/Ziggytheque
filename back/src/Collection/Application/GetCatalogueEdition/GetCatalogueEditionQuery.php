<?php

declare(strict_types=1);

namespace App\Collection\Application\GetCatalogueEdition;

final readonly class GetCatalogueEditionQuery
{
    public function __construct(
        public string $workTitle,
        public ?string $publisher = null,
        public ?string $specialEdition = null,
    ) {
    }
}
