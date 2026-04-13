<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MangaRepositoryInterface
{
    public function findById(string $id): ?Manga;

    /** @return Manga[] */
    public function search(string $query): array;

    public function save(Manga $manga): void;
}
