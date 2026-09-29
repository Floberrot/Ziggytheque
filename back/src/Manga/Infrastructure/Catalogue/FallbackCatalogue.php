<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Isbn;

/**
 * Asks the primary catalogue (BnF) first and only falls back to the secondary one
 * (Google Books) when the primary has nothing — one source answers, never both.
 */
final readonly class FallbackCatalogue implements CatalogueInterface
{
    public function __construct(
        private CatalogueInterface $primary,
        private CatalogueInterface $fallback,
    ) {
    }

    public function searchByTitle(string $title): array
    {
        return $this->primary->searchByTitle($title) ?: $this->fallback->searchByTitle($title);
    }

    public function searchByAuthor(string $author): array
    {
        return $this->primary->searchByAuthor($author) ?: $this->fallback->searchByAuthor($author);
    }

    public function findByIsbn(Isbn $isbn): array
    {
        return $this->primary->findByIsbn($isbn) ?: $this->fallback->findByIsbn($isbn);
    }
}
