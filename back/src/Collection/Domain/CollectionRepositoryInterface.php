<?php

declare(strict_types=1);

namespace App\Collection\Domain;

use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Application\GetWishlist\GetWishlistQuery;

interface CollectionRepositoryInterface
{
    public function findById(string $id): ?CollectionEntry;

    public function findByMangaId(string $mangaId): ?CollectionEntry;

    /**
     * The current user's entries for the given series.
     *
     * @param  list<string> $mangaIds
     * @return list<CollectionEntry>
     */
    public function findByMangaIds(array $mangaIds): array;

    /** @return CollectionEntry[] */
    public function findAll(): array;

    /** @return array{items: list<CollectionEntry>, total: int} */
    public function findFiltered(GetCollectionQuery $query): array;

    /**
     * Entries with at least one wished (non-owned) volume, filtered by title search and paginated.
     *
     * @return array{items: list<CollectionEntry>, total: int}
     */
    public function findWishedFiltered(GetWishlistQuery $query): array;

    /** @return CollectionEntry[] Only entries with notificationsEnabled = true */
    public function findFollowed(): array;

    public function save(CollectionEntry $entry): void;

    /** Removes the entry and, when it is the owner's own copy, the series under it (one flush). */
    public function delete(CollectionEntry $entry): void;
}
