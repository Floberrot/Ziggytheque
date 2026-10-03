<?php

declare(strict_types=1);

namespace App\Notification\Domain\Service;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Shared\Domain\ValueObject\WebLink;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Links the Jikan news of a series, already downloaded, to one followed entry: the news
 * that name the work become its articles (once each — a news already linked is skipped).
 */
final readonly class JikanArticleCollector
{
    /** News published before the feature existed are never linked. */
    private const string OLDEST_NEWS = '2026-04-01';

    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private MangaArticleMatcher $matcher,
    ) {
    }

    /** @param list<JikanNewsItem> $newsItems */
    public function collect(array $newsItems, CollectionEntry $entry): JikanFetchResult
    {
        $newCount   = 0;
        $oldestNews = new DateTimeImmutable(self::OLDEST_NEWS);

        foreach ($newsItems as $newsItem) {
            $url = $newsItem->url;
            // The link ends up in an href: http(s) only.
            if ($url === null || !WebLink::isWebLink($url)) {
                continue;
            }

            // The work must be named in the article — MAL tags alone are not enough.
            if (!$this->matcher->mentions($entry->manga->title, $newsItem->title . ' ' . $newsItem->excerpt)) {
                continue;
            }

            if ($this->articleRepository->existsByCollectionEntryAndUrl($entry->id, $url)) {
                continue;
            }

            $publishedAt = $newsItem->date !== null ? new DateTimeImmutable($newsItem->date) : null;
            if ($publishedAt !== null && $publishedAt < $oldestNews) {
                continue;
            }

            $article = new Article(
                id: Uuid::v4()->toRfc4122(),
                collectionEntry: $entry,
                title: mb_substr($newsItem->title !== '' ? $newsItem->title : 'Jikan News', 0, 500),
                url: $url,
                sourceName: 'jikan-news',
                author: $newsItem->author,
                imageUrl: null,
                publishedAt: $publishedAt,
                snippet: $newsItem->excerpt !== '' ? mb_substr($newsItem->excerpt, 0, 500) : null,
            );
            $article->owner = $entry->owner;
            $this->articleRepository->save($article);
            ++$newCount;
        }

        return new JikanFetchResult($newCount, count($newsItems));
    }
}
