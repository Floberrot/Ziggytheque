<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MangaRepositoryInterface
{
    public function findById(string $id): ?Manga;

    /** @return Manga[] */
    public function search(string $query): array;

    /** @return Manga[] */
    public function findAllPaginated(int $offset, int $limit): array;

    public function countAll(): int;

    public function save(Manga $manga): void;
}
