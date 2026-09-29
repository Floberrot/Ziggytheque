<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MangaRepositoryInterface
{
    public function findById(string $id): ?Manga;

    /** @return Manga[] */
    public function search(string $query): array;

    /**
     * Series whose title equals one of the given titles, case-insensitively — the
     * candidates an {@see EditionIdentity} is then matched against.
     *
     * @param  list<string> $titles
     * @return list<Manga>
     */
    public function findByTitles(array $titles): array;

    /** @return Manga[] */
    public function findAllPaginated(int $offset, int $limit): array;

    public function countAll(): int;

    public function save(Manga $manga): void;
}
