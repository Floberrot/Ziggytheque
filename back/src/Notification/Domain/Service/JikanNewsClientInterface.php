<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

interface JikanNewsClientInterface
{
    /**
     * Downloads the news Jikan lists for one MyAnimeList series — once per crawl, however
     * many accounts follow their own copy of it.
     *
     * @return list<JikanNewsItem>
     */
    public function fetchNews(string $malId): array;
}
