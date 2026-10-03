<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Shared\Domain\Text\TextFold;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps catalogue answers for an hour: scanning a whole shelf of one series asks the
 * catalogue for the same work again and again. An empty answer is kept five minutes
 * (a real "nothing found" — an outage raises CatalogueUnavailableException, which is
 * never cached).
 */
final readonly class CachedCatalogue implements CatalogueInterface
{
    private const int TTL_SECONDS = 3600;
    private const int EMPTY_TTL_SECONDS = 300;

    public function __construct(
        private CatalogueInterface $inner,
        private CacheInterface $cache,
    ) {
    }

    public function searchByTitle(string $title): array
    {
        return $this->remember(
            'title',
            TextFold::fold($title),
            fn (): array => $this->inner->searchByTitle($title),
        );
    }

    public function searchByAuthor(string $author): array
    {
        return $this->remember(
            'author',
            TextFold::fold($author),
            fn (): array => $this->inner->searchByAuthor($author),
        );
    }

    public function findByIsbn(Isbn $isbn): array
    {
        return $this->remember('isbn', $isbn->value, fn (): array => $this->inner->findByIsbn($isbn));
    }

    /**
     * @param  callable(): list<CatalogueRecord> $fetch
     * @return list<CatalogueRecord>
     */
    private function remember(string $mode, string $term, callable $fetch): array
    {
        if ($term === '') {
            return [];
        }

        $cacheKey = sprintf('catalogue_%s_%s', $mode, hash('xxh128', $term));

        /** @var list<CatalogueRecord> $records */
        $records = $this->cache->get($cacheKey, static function (ItemInterface $item) use ($fetch): array {
            $records = $fetch();
            $item->expiresAfter($records === [] ? self::EMPTY_TTL_SECONDS : self::TTL_SECONDS);

            return $records;
        });

        return $records;
    }
}
