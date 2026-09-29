<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

final readonly class CatalogueSearchResult
{
    /** @param list<CatalogueEdition> $editions */
    public function __construct(
        public string $searchedText,
        public ?int $requestedVolume,
        public array $editions,
    ) {
    }
}
