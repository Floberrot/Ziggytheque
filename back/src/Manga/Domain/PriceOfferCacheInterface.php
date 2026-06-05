<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface PriceOfferCacheInterface
{
    /**
     * Returns the cached offers for the given ISBN + marketplace, or null if
     * no cached entry exists (miss or expired).
     *
     * @return list<array<string, mixed>>|null
     */
    public function get(Isbn $isbn, Marketplace $marketplace): ?array;

    /**
     * Stores the offers for the given ISBN + marketplace.
     *
     * @param list<array<string, mixed>> $offers
     */
    public function put(Isbn $isbn, Marketplace $marketplace, array $offers): void;
}
