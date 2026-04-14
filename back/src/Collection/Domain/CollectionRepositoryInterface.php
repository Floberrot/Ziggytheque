<?php

declare(strict_types=1);

namespace App\Collection\Domain;

interface CollectionRepositoryInterface
{
    public function findById(string $id): ?CollectionEntry;

    public function findByMangaId(string $mangaId): ?CollectionEntry;

    /** @return CollectionEntry[] */
    public function findAll(): array;

    /** @return CollectionEntry[] entries that have at least one wishlisted (non-owned) volume */
    public function findWithWishlistVolumes(): array;

    public function save(CollectionEntry $entry): void;

    public function delete(CollectionEntry $entry): void;
}
