<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

use App\Manga\Domain\Isbn;

/**
 * French book catalogue (legal deposit first). Returns individual volume records —
 * {@see \App\Manga\Domain\Service\CatalogueEditionAssembler} turns them into series.
 * Implementations never throw: an unreachable catalogue yields an empty list.
 */
interface CatalogueInterface
{
    /** @return list<CatalogueRecord> */
    public function searchByTitle(string $title): array;

    /** @return list<CatalogueRecord> */
    public function searchByAuthor(string $author): array;

    /** @return list<CatalogueRecord> */
    public function findByIsbn(Isbn $isbn): array;
}
