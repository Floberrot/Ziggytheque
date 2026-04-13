<?php

declare(strict_types=1);

namespace App\Wishlist\Domain;

interface WishlistRepositoryInterface
{
    public function findById(string $id): ?WishlistItem;

    /** @return WishlistItem[] */
    public function findAll(): array;

    public function save(WishlistItem $item): void;

    public function delete(WishlistItem $item): void;
}
