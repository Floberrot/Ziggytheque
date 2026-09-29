<?php

declare(strict_types=1);

namespace App\Collection\Application\AddFromCatalogue;

use App\Manga\Domain\Catalogue\CatalogueEdition;

final readonly class AddFromCatalogueCommand
{
    /** @param list<int> $ownedNumbers tomes to mark as owned — may be empty */
    public function __construct(
        public CatalogueEdition $edition,
        public array $ownedNumbers,
    ) {
    }
}
