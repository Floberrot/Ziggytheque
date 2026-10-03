<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Fetch;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchRssFeedHandler;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Domain\Article;
use App\Notification\Domain\ArticleRepositoryInterface;
use App\Notification\Domain\FollowedSeries;
use App\Notification\Domain\Service\MangaArticleMatcher;
use App\Notification\Domain\Service\RssArticleCollector;
use App\Notification\Domain\Service\RssFeedItem;
use App\Notification\Domain\Service\RssFeedParserException;
use App\Notification\Shared\Event\RssFetchFailedEvent;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use App\Notification\Shared\Event\RssFetchSucceededEvent;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Notification\InMemoryArticleRepository;
use App\Tests\Doubles\Notification\InMemoryRssFeedParser;
use App\Tests\Doubles\Shared\RecordingEventBus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FetchRssFeedHandlerTest extends TestCase
{
    private const string FEED_URL = 'https://news.example/feed';

    private InMemoryRssFeedParser $feedParser;
    private InMemoryArticleRepository $articleRepository;
    private RecordingEventBus $eventBus;

    /** @var array<string, CollectionEntry> */
    private array $entries = [];

    protected function setUp(): void
    {
        $this->feedParser        = new InMemoryRssFeedParser();
        $this->articleRepository = new InMemoryArticleRepository();
        $this->eventBus          = new RecordingEventBus();

        $this->entries = [
            'entry-a' => FollowedEntryFactory::entry('entry-a', 'One Piece', owner: FollowedEntryFactory::owner('owner-a')),
            'entry-b' => FollowedEntryFactory::entry('entry-b', 'One Piece', owner: FollowedEntryFactory::owner('owner-b')),
            'entry-c' => FollowedEntryFactory::entry('entry-c', 'Naruto', owner: FollowedEntryFactory::owner('owner-a')),
        ];

        $this->feedParser->serve(self::FEED_URL, [
            $this->item('One Piece : le tome 110', 'https://news.example/one-piece'),
            $this->item('Naruto : nouvelle édition', 'https://news.example/naruto'),
            $this->item('Berserk : la suite', 'https://news.example/berserk'),
        ]);
    }

    public function testDownloadsTheFeedOnceForEveryFollowedSeries(): void
    {
        $this->handle($this->message('entry-a', 'entry-b', 'entry-c'));

        $this->assertSame(1, $this->feedParser->downloadsOf(self::FEED_URL));
    }

    public function testLinksEachItemToTheSeriesItNames(): void
    {
        $this->handle($this->message('entry-a', 'entry-b', 'entry-c'));

        $this->assertSame(['https://news.example/one-piece'], $this->urlsOf('entry-a'));
        $this->assertSame(['https://news.example/one-piece'], $this->urlsOf('entry-b'));
        $this->assertSame(['https://news.example/naruto'], $this->urlsOf('entry-c'));
        // Each account gets its own article, owned by it.
        $this->assertSame('owner-b', $this->articleRepository->articlesOf('entry-b')[0]->owner?->id);
    }

    public function testEachSeriesKeepsItsOwnJournalLine(): void
    {
        $this->handle($this->message('entry-a', 'entry-c'));

        $started   = $this->eventBus->eventsOf(RssFetchStartedEvent::class);
        $succeeded = $this->eventBus->eventsOf(RssFetchSucceededEvent::class);

        $this->assertSame(['entry-a', 'entry-c'], array_map(static fn ($event) => $event->collectionEntryId, $started));
        $this->assertSame('manga-news', $started[0]->feedName);
        $this->assertSame(self::FEED_URL, $started[0]->feedUrl);
        $this->assertSame('One Piece', $started[0]->mangaTitle);

        $this->assertCount(2, $succeeded);
        $this->assertSame($started[0]->correlationId, $succeeded[0]->correlationId);
        $this->assertSame($started[1]->correlationId, $succeeded[1]->correlationId);
        $this->assertSame(1, $succeeded[0]->newCount);
        $this->assertSame(3, $succeeded[0]->itemsScanned);
        $this->assertSame('Naruto', $succeeded[1]->mangaTitle);
        $this->assertSame([], $this->eventBus->eventsOf(RssFetchFailedEvent::class));
    }

    public function testASeriesRemovedSinceThePlanIsSkipped(): void
    {
        $this->handle($this->message('entry-a', 'entry-gone'));

        $this->assertCount(1, $this->eventBus->eventsOf(RssFetchStartedEvent::class));
        $this->assertSame(['https://news.example/one-piece'], $this->urlsOf('entry-a'));
    }

    public function testNothingLeftToMatchMeansNoDownload(): void
    {
        $this->handle($this->message('entry-gone'));

        $this->assertSame(0, $this->feedParser->downloadsOf(self::FEED_URL));
        $this->assertSame([], $this->eventBus->events);
    }

    /** A feed that cannot be read fails every series' line, then the message is retried. */
    public function testAFeedFailureFailsEverySeriesAndIsRethrown(): void
    {
        $this->feedParser->fail(self::FEED_URL, RssFeedParserException::httpError(503));

        try {
            $this->handle($this->message('entry-a', 'entry-c'));
            $this->fail('The feed failure should reach the worker, which retries the message.');
        } catch (RssFeedParserException $exception) {
            $this->assertSame('HTTP 503', $exception->getMessage());
        }

        $failed = $this->eventBus->eventsOf(RssFetchFailedEvent::class);
        $this->assertSame(['entry-a', 'entry-c'], array_map(static fn ($event) => $event->collectionEntryId, $failed));
        $this->assertSame('HTTP 503', $failed[0]->error);
        $this->assertSame(RssFeedParserException::class, $failed[0]->exceptionClass);
        $this->assertSame(
            $this->eventBus->eventsOf(RssFetchStartedEvent::class)[1]->correlationId,
            $failed[1]->correlationId,
        );
        $this->assertSame([], $this->articleRepository->articles);
    }

    public function testOneSeriesFailingNeverCostsTheOthersTheirArticles(): void
    {
        $articleRepository = new class ($this->articleRepository) implements ArticleRepositoryInterface {
            public function __construct(private readonly InMemoryArticleRepository $inner)
            {
            }

            public function existsByCollectionEntryAndUrl(string $collectionEntryId, string $url): bool
            {
                if ($collectionEntryId === 'entry-a') {
                    throw new RuntimeException('cannot read entry-a');
                }

                return $this->inner->existsByCollectionEntryAndUrl($collectionEntryId, $url);
            }

            public function save(Article $article): void
            {
                $this->inner->save($article);
            }

            public function findPaginated(int $page, int $limit, ?string $collectionEntryId): array
            {
                return $this->inner->findPaginated($page, $limit, $collectionEntryId);
            }

            public function findCreatedSince(DateTimeImmutable $since): array
            {
                return $this->inner->findCreatedSince($since);
            }
        };

        $this->handle($this->message('entry-a', 'entry-b'), new RssArticleCollector($articleRepository, new MangaArticleMatcher()));

        $failed = $this->eventBus->eventsOf(RssFetchFailedEvent::class);
        $this->assertSame(['entry-a'], array_map(static fn ($event) => $event->collectionEntryId, $failed));
        $this->assertSame('cannot read entry-a', $failed[0]->error);
        $this->assertSame(['entry-b'], array_map(
            static fn ($event) => $event->collectionEntryId,
            $this->eventBus->eventsOf(RssFetchSucceededEvent::class),
        ));
        $this->assertSame(['https://news.example/one-piece'], $this->urlsOf('entry-b'));
    }

    private function handle(FetchRssFeedMessage $message, ?RssArticleCollector $collector = null): void
    {
        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findById')->willReturnCallback(
            fn (string $entryId): ?CollectionEntry => $this->entries[$entryId] ?? null,
        );

        $handler = new FetchRssFeedHandler(
            $collectionRepository,
            $this->feedParser,
            $collector ?? new RssArticleCollector($this->articleRepository, new MangaArticleMatcher()),
            $this->eventBus,
        );

        $handler($message);
    }

    private function message(string ...$entryIds): FetchRssFeedMessage
    {
        return new FetchRssFeedMessage(
            feedName: 'manga-news',
            feedUrl: self::FEED_URL,
            followedSeries: array_map(
                fn (string $entryId): FollowedSeries => new FollowedSeries(
                    $entryId,
                    isset($this->entries[$entryId]) ? $this->entries[$entryId]->manga->title : 'Gone',
                ),
                array_values($entryIds),
            ),
            crawlJobId: 'job-1',
            crawlRunId: 'run-1',
        );
    }

    private function item(string $title, string $url): RssFeedItem
    {
        return new RssFeedItem($title, '', $url, new DateTimeImmutable('2026-05-01 10:00:00'), null);
    }

    /** @return list<string> */
    private function urlsOf(string $entryId): array
    {
        return array_map(
            static fn (Article $article): string => $article->url,
            $this->articleRepository->articlesOf($entryId),
        );
    }
}
