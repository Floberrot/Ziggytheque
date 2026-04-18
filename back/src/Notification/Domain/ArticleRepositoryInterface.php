<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface ArticleRepositoryInterface
{
    public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool;

    public function save(Article $article): void;

    /**
     * @return array{items: Article[], total: int}
     */
    public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array;
}
