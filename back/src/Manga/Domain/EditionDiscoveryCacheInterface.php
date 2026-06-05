<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface EditionDiscoveryCacheInterface
{
    /**
     * Cached discovery result for (query, author, language), or null on a miss.
     *
     * @return list<array<string, mixed>>|null
     */
    public function get(string $query, ?string $author, ?string $language): ?array;

    /**
     * Stores a discovery result. Implementations may use a shorter TTL for empty
     * results so a transient upstream failure is not cached as "no editions" for long.
     *
     * @param list<array<string, mixed>> $editions
     */
    public function put(string $query, ?string $author, ?string $language, array $editions): void;
}
