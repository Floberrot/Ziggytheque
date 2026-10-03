<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Notification\Domain\Service\JikanArticleCollector;
use App\Notification\Domain\Service\JikanNewsItem;
use App\Notification\Domain\Service\MangaArticleMatcher;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Notification\InMemoryArticleRepository;
use PHPUnit\Framework\TestCase;

final class JikanArticleCollectorTest extends TestCase
{
    private InMemoryArticleRepository $articleRepository;
    private JikanArticleCollector $collector;

    protected function setUp(): void
    {
        $this->articleRepository = new InMemoryArticleRepository();
        $this->collector         = new JikanArticleCollector($this->articleRepository, new MangaArticleMatcher());
    }

    public function testLinksTheNewsThatNameTheWork(): void
    {
        $owner = FollowedEntryFactory::owner('owner-a');
        $entry = FollowedEntryFactory::entry('entry-a', 'One Piece', '13', $owner);

        $result = $this->collector->collect([
            $this->news('https://myanimelist.net/news/1', 'One Piece anime returns', 'The Egghead arc.'),
            $this->news('https://myanimelist.net/news/2', 'Spring season preview', 'Many shows.'),
        ], $entry);

        $this->assertSame(1, $result->newCount);
        $this->assertSame(2, $result->itemsReceived);

        $article = $this->articleRepository->articles[0];
        $this->assertSame($entry, $article->collectionEntry);
        $this->assertSame($owner, $article->owner);
        $this->assertSame('One Piece anime returns', $article->title);
        $this->assertSame('jikan-news', $article->sourceName);
        $this->assertSame('mal-editor', $article->author);
        $this->assertNull($article->imageUrl);
        $this->assertSame('The Egghead arc.', $article->snippet);
        $this->assertSame('2026-05-01', $article->publishedAt?->format('Y-m-d'));
    }

    /** MAL tags alone are not enough: the excerpt or the title must name the work. */
    public function testMatchesOnTheCurrentTitleOfTheSeries(): void
    {
        $entry = FollowedEntryFactory::entry('entry-a', 'Berserk', '2');

        $result = $this->collector->collect([
            $this->news('https://myanimelist.net/news/3', 'Weekly news', 'A new Berserk chapter is out.'),
        ], $entry);

        $this->assertSame(1, $result->newCount);
    }

    public function testSkipsNewsWithoutAWebLink(): void
    {
        $result = $this->collector->collect([
            $this->news(null, 'One Piece news'),
            $this->news('ftp://myanimelist.net/news/4', 'One Piece news'),
        ], FollowedEntryFactory::entry('entry-a', 'One Piece', '13'));

        $this->assertSame(0, $result->newCount);
        $this->assertSame(2, $result->itemsReceived);
    }

    public function testSkipsNewsOlderThanTheFeature(): void
    {
        $result = $this->collector->collect([
            $this->news('https://myanimelist.net/news/5', 'One Piece old', date: '2026-03-01T00:00:00+00:00'),
            $this->news('https://myanimelist.net/news/6', 'One Piece undated', date: null),
        ], FollowedEntryFactory::entry('entry-a', 'One Piece', '13'));

        $this->assertSame(1, $result->newCount);
        $this->assertSame('https://myanimelist.net/news/6', $this->articleRepository->articles[0]->url);
        $this->assertNull($this->articleRepository->articles[0]->publishedAt);
    }

    public function testANewsAlreadyLinkedIsNotLinkedAgain(): void
    {
        $entry = FollowedEntryFactory::entry('entry-a', 'One Piece', '13');
        $news  = [$this->news('https://myanimelist.net/news/7', 'One Piece news')];

        $this->collector->collect($news, $entry);
        $second = $this->collector->collect($news, $entry);

        $this->assertSame(0, $second->newCount);
        $this->assertCount(1, $this->articleRepository->articles);
    }

    public function testAnUntitledNewsIsCalledJikanNewsAndAnEmptyExcerptGivesNoSnippet(): void
    {
        $this->collector->collect(
            [$this->news('https://myanimelist.net/news/8', '', 'One Piece mentioned only here.')],
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
        );
        $this->collector->collect(
            [$this->news('https://myanimelist.net/news/9', 'One Piece headline', '')],
            FollowedEntryFactory::entry('entry-b', 'One Piece', '13'),
        );

        $this->assertSame('Jikan News', $this->articleRepository->articles[0]->title);
        $this->assertNull($this->articleRepository->articles[1]->snippet);
    }

    public function testCutsLongTitlesAndExcerpts(): void
    {
        $this->collector->collect(
            [$this->news('https://myanimelist.net/news/10', 'One Piece ' . str_repeat('t', 600), str_repeat('e', 600))],
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
        );

        $article = $this->articleRepository->articles[0];
        $this->assertSame(500, mb_strlen($article->title));
        $this->assertSame(500, mb_strlen((string) $article->snippet));
    }

    private function news(
        ?string $url,
        string $title,
        string $excerpt = '',
        ?string $date = '2026-05-01T10:00:00+00:00',
    ): JikanNewsItem {
        return new JikanNewsItem($url, $title, $excerpt, 'mal-editor', $date);
    }
}
