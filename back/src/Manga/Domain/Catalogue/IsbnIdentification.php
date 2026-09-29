<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

/** A scanned book, placed in its series: which edition, which tome. */
final readonly class IsbnIdentification
{
    public function __construct(
        public CatalogueEdition $edition,
        public CatalogueVolume $volume,
    ) {
    }
}
