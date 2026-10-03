<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Notification;

use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use DateTimeImmutable;

/** Articles kept in memory, in the order they were saved. */
final class InMemoryArticleRepository implements ArticleRepositoryInterface
{
    /** @var list<Article> */
    public array $articles = [];

    public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool
    {
        foreach ($this->articles as $article) {
            if ($article->collectionEntry->id === $collectionEntryId && $article->url === $url) {
                return true;
            }
        }

        return false;
    }

    public function save(Article $article): void
    {
        $this->articles[] = $article;
    }

    public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array
    {
        $articles = array_values(array_filter(
            $this->articles,
            static fn (Article $article): bool => $collectionEntryId === null
                || $article->collectionEntry->id === $collectionEntryId,
        ));

        return [
            'items' => array_slice($articles, ($page - 1) * $limit, $limit),
            'total' => count($articles),
        ];
    }

    public function findCreatedSince(DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->articles,
            static fn (Article $article): bool => $article->createdAt >= $since
                && $article->collectionEntry->notificationsEnabled,
        ));
    }

    /** @return list<Article> */
    public function articlesOf(string $collectionEntryId): array
    {
        return array_values(array_filter(
            $this->articles,
            static fn (Article $article): bool => $article->collectionEntry->id === $collectionEntryId,
        ));
    }
}
