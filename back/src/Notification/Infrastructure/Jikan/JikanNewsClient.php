<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Jikan;

use App\Notification\Domain\Service\JikanNewsClientInterface;
use App\Notification\Domain\Service\JikanNewsItem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads the news Jikan (MyAnimeList) lists for a series; matching them against the
 * followed copies is {@see \App\Notification\Domain\Service\JikanArticleCollector}'s job.
 */
final readonly class JikanNewsClient implements JikanNewsClientInterface
{
    private const BASE_URL = 'https://api.jikan.moe/v4';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function fetchNews(string $malId): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/manga/' . $malId . '/news',
            ['timeout' => 10],
        );
        $data  = $response->toArray();
        $items = $data['data'] ?? [];

        $newsItems = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $newsItems[] = new JikanNewsItem(
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
                title: (string) ($item['title'] ?? ''),
                excerpt: (string) ($item['excerpt'] ?? ''),
                author: isset($item['author_username']) ? (string) $item['author_username'] : null,
                date: isset($item['date']) ? (string) $item['date'] : null,
            );
        }

        return $newsItems;
    }
}
