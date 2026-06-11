<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Jikan;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\Service\JikanFetchResult;
use App\Notification\Domain\Service\JikanNewsClientInterface;
use App\Notification\Domain\Service\MangaArticleMatcher;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JikanNewsClient implements JikanNewsClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ArticleRepositoryInterface $articleRepository,
        private MangaArticleMatcher $matcher,
    ) {
    }

    public function fetch(string $malId, CollectionEntry $entry): JikanFetchResult
    {
        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/manga/' . $malId . '/news',
            ['timeout' => 10],
        );
        $data  = $response->toArray();
        $items = $data['data'] ?? [];

        $newCount = 0;
        foreach ($items as $item) {
            $url = $item['url'] ?? null;
            if ($url === null) {
                continue;
            }
            $itemTitle   = (string) ($item['title'] ?? '');
            $itemExcerpt = (string) ($item['excerpt'] ?? '');
            // The work must be named in the article — MAL tags alone are not enough.
            if (!$this->matcher->mentions($entry->manga->title, $itemTitle . ' ' . $itemExcerpt)) {
                continue;
            }
            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $url)) {
                continue;
            }
            $publishedAt = isset($item['date']) ? new DateTimeImmutable($item['date']) : null;
            if ($publishedAt !== null && $publishedAt < new DateTimeImmutable('2026-04-01')) {
                continue;
            }
            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr($itemTitle !== '' ? $itemTitle : 'Jikan News', 0, 500),
                url: $url,
                sourceName: 'jikan-news',
                author: $item['author_username'] ?? null,
                imageUrl: null,
                publishedAt: $publishedAt,
                snippet: $itemExcerpt !== '' ? mb_substr($itemExcerpt, 0, 500) : null,
            );
            $article->owner = $entry->owner;
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new JikanFetchResult($newCount, count($items));
    }
}
