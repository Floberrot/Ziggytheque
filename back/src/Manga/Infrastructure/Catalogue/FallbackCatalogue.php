<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Isbn;

/**
 * Asks the primary catalogue (BnF) first and only falls back to the secondary one
 * (Google Books) when the primary has nothing — one source answers, never both.
 *
 * When the primary is down, the fallback still answers; "nothing found" is only
 * claimed when a source that did answer found nothing. A primary outage with an
 * empty fallback stays an outage: the book may well exist.
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
        return $this->ask(
            fn (): array => $this->primary->searchByTitle($title),
            fn (): array => $this->fallback->searchByTitle($title),
        );
    }

    public function searchByAuthor(string $author): array
    {
        return $this->ask(
            fn (): array => $this->primary->searchByAuthor($author),
            fn (): array => $this->fallback->searchByAuthor($author),
        );
    }

    public function findByIsbn(Isbn $isbn): array
    {
        return $this->ask(
            fn (): array => $this->primary->findByIsbn($isbn),
            fn (): array => $this->fallback->findByIsbn($isbn),
        );
    }

    /**
     * @param  callable(): list<CatalogueRecord> $askPrimary
     * @param  callable(): list<CatalogueRecord> $askFallback
     * @return list<CatalogueRecord>
     */
    private function ask(callable $askPrimary, callable $askFallback): array
    {
        $primaryOutage = null;
        try {
            $records = $askPrimary();
            if ($records !== []) {
                return $records;
            }
        } catch (CatalogueUnavailableException $outage) {
            $primaryOutage = $outage;
        }

        try {
            $fallbackRecords = $askFallback();
        } catch (CatalogueUnavailableException $fallbackOutage) {
            // The primary did answer "nothing": that answer stands.
            if ($primaryOutage === null) {
                return [];
            }

            throw $primaryOutage;
        }

        if ($fallbackRecords === [] && $primaryOutage !== null) {
            throw $primaryOutage;
        }

        return $fallbackRecords;
    }
}
