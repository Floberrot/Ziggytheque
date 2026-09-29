<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

use App\Manga\Domain\Isbn;

/**
 * One printed book as a French catalogue describes it, already split into its parts:
 * the work, the edition qualifier written in the title head ("Berserk : prestige. 1"
 * → "prestige"), the volume number and whatever follows it (a volume title, or an
 * edition name some sources append: "Berserk - Tome 1 - Édition prestige").
 */
final readonly class CatalogueRecord
{
    public function __construct(
        public string $workTitle,
        public ?string $headQualifier,
        public ?int $volumeNumber,
        public ?string $trailingQualifier,
        public ?string $publisher,
        public ?string $author,
        public ?Isbn $isbn,
        public ?string $coverUrl,
        public string $source,
    ) {
    }
}
