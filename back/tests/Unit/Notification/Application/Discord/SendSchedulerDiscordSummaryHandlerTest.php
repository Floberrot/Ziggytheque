<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Discord;

use App\Collection\Domain\CollectionEntry;
use App\Notification\Application\Discord\SendSchedulerDiscordSummaryHandler;
use App\Notification\Application\Discord\SendSchedulerDiscordSummaryMessage;
use App\Notification\Domain\Article;
use App\Notification\Domain\DiscordNotifierInterface;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Notification\InMemoryArticleRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SendSchedulerDiscordSummaryHandlerTest extends TestCase
{
    private InMemoryArticleRepository $articleRepository;

    /** @var list<array<int, array{mangaTitle: string, mangaCoverUrl: string|null, articles: array<int, array{title: string, url: string}>}>> */
    private array $summaries = [];

    protected function setUp(): void
    {
        $this->articleRepository = new InMemoryArticleRepository();
    }

    public function testSendsTheArticlesOfTheRunGroupedBySeries(): void
    {
        $onePiece = FollowedEntryFactory::entry('entry-a', 'One Piece', coverUrl: 'https://cover.example/op.jpg');
        $naruto   = FollowedEntryFactory::entry('entry-b', 'Naruto');
        $this->article($onePiece, 'One Piece 110', 'https://news.example/1');
        $this->article($naruto, 'Naruto returns', 'https://news.example/2');
        $this->article($onePiece, 'One Piece anime', 'https://news.example/3');

        $this->handle(new DateTimeImmutable('-1 minute'));

        $this->assertSame([[
            [
                'mangaTitle'    => 'One Piece',
                'mangaCoverUrl' => 'https://cover.example/op.jpg',
                'articles'      => [
                    ['title' => 'One Piece 110', 'url' => 'https://news.example/1'],
                    ['title' => 'One Piece anime', 'url' => 'https://news.example/3'],
                ],
            ],
            [
                'mangaTitle'    => 'Naruto',
                'mangaCoverUrl' => null,
                'articles'      => [['title' => 'Naruto returns', 'url' => 'https://news.example/2']],
            ],
        ]], $this->summaries);
    }

    public function testOnlyTheArticlesCreatedSinceTheRunStarted(): void
    {
        $this->article(FollowedEntryFactory::entry('entry-a', 'One Piece'), 'One Piece 110', 'https://news.example/1');

        $this->handle(new DateTimeImmutable('+1 minute'));

        $this->assertSame([], $this->summaries);
    }

    public function testNoNewArticleMeansNoMessage(): void
    {
        $this->handle(new DateTimeImmutable('-1 day'));

        $this->assertSame([], $this->summaries);
    }

    private function handle(DateTimeImmutable $runStartedAt): void
    {
        $test    = $this;
        $discord = new class ($test) implements DiscordNotifierInterface {
            public function __construct(private readonly SendSchedulerDiscordSummaryHandlerTest $test)
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendNewArticles(string $mangaTitle, ?string $mangaCoverUrl, int $count, array $articles): void
            {
            }

            public function sendSchedulerSummary(array $entries): void
            {
                $this->test->recordSummary($entries);
            }

            public function sendAlert(string $title, string $description, bool $critical = false): void
            {
            }
        };

        (new SendSchedulerDiscordSummaryHandler($this->articleRepository, $discord))(
            new SendSchedulerDiscordSummaryMessage($runStartedAt),
        );
    }

    /** @param array<int, array{mangaTitle: string, mangaCoverUrl: string|null, articles: array<int, array{title: string, url: string}>}> $entries */
    public function recordSummary(array $entries): void
    {
        $this->summaries[] = $entries;
    }

    private function article(CollectionEntry $entry, string $title, string $url): void
    {
        $this->articleRepository->save(new Article(
            id: $url,
            collectionEntry: $entry,
            title: $title,
            url: $url,
            sourceName: 'rss',
            author: null,
            imageUrl: null,
            publishedAt: null,
        ));
    }
}
