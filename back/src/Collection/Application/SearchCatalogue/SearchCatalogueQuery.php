<?php

declare(strict_types=1);

namespace App\Collection\Application\SearchCatalogue;

use App\Manga\Domain\Catalogue\CatalogueSearchModeEnum;

final readonly class SearchCatalogueQuery
{
    public function __construct(
        public string $query,
        public CatalogueSearchModeEnum $mode = CatalogueSearchModeEnum::Title,
    ) {
    }
}
