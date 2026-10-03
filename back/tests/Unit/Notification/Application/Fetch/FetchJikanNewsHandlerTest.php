<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Fetch;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsHandler;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Domain\Article;
use App\Notification\Domain\FollowedSeries;
use App\Notification\Domain\Service\JikanArticleCollector;
use App\Notification\Domain\Service\JikanNewsItem;
use App\Notification\Domain\Service\MangaArticleMatcher;
use App\Notification\Shared\Event\JikanFetchFailedEvent;
use App\Notification\Shared\Event\JikanFetchStartedEvent;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Notification\InMemoryArticleRepository;
use App\Tests\Doubles\Notification\InMemoryJikanNewsClient;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FetchJikanNewsHandlerTest extends TestCase
{
    private const string MAL_ID = '13';

    private InMemoryJikanNewsClient $jikanNewsClient;
    private InMemoryArticleRepository $articleRepository;
    private RecordingEventBus $eventBus;

    /** @var array<string, CollectionEntry> */
    private array $entries = [];

    protected function setUp(): void
    {
        $this->jikanNewsClient   = new InMemoryJikanNewsClient();
        $this->articleRepository = new InMemoryArticleRepository();
        $this->eventBus          = new RecordingEventBus();

        // Two accounts, each following its own copy of the same MyAnimeList series.
        $this->entries = [
            'entry-a' => FollowedEntryFactory::entry('entry-a', 'One Piece', self::MAL_ID, FollowedEntryFactory::owner('owner-a'), 'https://cover.example/a.jpg'),
            'entry-b' => FollowedEntryFactory::entry('entry-b', 'One Piece', self::MAL_ID, FollowedEntryFactory::owner('owner-b')),
        ];

        $this->jikanNewsClient->serve(self::MAL_ID, [
            new JikanNewsItem('https://myanimelist.net/news/1', 'One Piece anime returns', '', 'mal-editor', '2026-05-01T10:00:00+00:00'),
            new JikanNewsItem('https://myanimelist.net/news/2', 'Season preview', 'Many shows.', null, null),
        ]);
    }

    public function testDownloadsTheNewsOnceForEveryFollowedCopy(): void
    {
        $this->handle($this->message('entry-a', 'entry-b'));

        $this->assertSame(1, $this->jikanNewsClient->downloadsOf(self::MAL_ID));
        $this->assertSame(['https://myanimelist.net/news/1'], $this->urlsOf('entry-a'));
        $this->assertSame(['https://myanimelist.net/news/1'], $this->urlsOf('entry-b'));
        $this->assertSame('owner-b', $this->articleRepository->articlesOf('entry-b')[0]->owner?->id);
    }

    public function testEachCopyKeepsItsOwnJournalLine(): void
    {
        $this->handle($this->message('entry-a', 'entry-b'));

        $started   = $this->eventBus->eventsOf(JikanFetchStartedEvent::class);
        $succeeded = $this->eventBus->eventsOf(JikanFetchSucceededEvent::class);

        $this->assertSame(['entry-a', 'entry-b'], array_map(static fn ($event) => $event->collectionEntryId, $started));
        $this->assertSame(self::MAL_ID, $started[0]->malId);
        $this->assertSame('One Piece', $started[0]->mangaTitle);

        $this->assertSame($started[0]->correlationId, $succeeded[0]->correlationId);
        $this->assertSame($started[1]->correlationId, $succeeded[1]->correlationId);
        $this->assertSame(1, $succeeded[0]->newCount);
        $this->assertSame(2, $succeeded[0]->itemsReceived);
        $this->assertSame('https://cover.example/a.jpg', $succeeded[0]->mangaCoverUrl);
        $this->assertSame([], $this->eventBus->eventsOf(JikanFetchFailedEvent::class));
    }

    public function testACopyRemovedSinceThePlanIsSkipped(): void
    {
        $this->handle($this->message('entry-gone', 'entry-b'));

        $this->assertSame(['entry-b'], array_map(
            static fn ($event) => $event->collectionEntryId,
            $this->eventBus->eventsOf(JikanFetchStartedEvent::class),
        ));
    }

    public function testNothingLeftToMatchMeansNoDownload(): void
    {
        $this->handle($this->message('entry-gone'));

        $this->assertSame(0, $this->jikanNewsClient->downloadsOf(self::MAL_ID));
        $this->assertSame([], $this->eventBus->events);
    }

    public function testADownloadFailureFailsEveryCopyAndIsRethrown(): void
    {
        $this->jikanNewsClient->fail(self::MAL_ID, new RuntimeException('Jikan 429'));

        try {
            $this->handle($this->message('entry-a', 'entry-b'));
            $this->fail('The download failure should reach the worker, which retries the message.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Jikan 429', $exception->getMessage());
        }

        $failed = $this->eventBus->eventsOf(JikanFetchFailedEvent::class);
        $this->assertSame(['entry-a', 'entry-b'], array_map(static fn ($event) => $event->collectionEntryId, $failed));
        $this->assertSame('Jikan 429', $failed[0]->error);
        $this->assertSame(RuntimeException::class, $failed[0]->exceptionClass);
        $this->assertSame(self::MAL_ID, $failed[0]->malId);
        $this->assertSame([], $this->articleRepository->articles);
    }

    public function testOneCopyFailingNeverCostsTheOthersTheirArticles(): void
    {
        // A news date that cannot be read fails only the copies it matches.
        $this->jikanNewsClient->serve(self::MAL_ID, [
            new JikanNewsItem('https://myanimelist.net/news/3', 'One Piece special', '', null, 'not a date'),
            new JikanNewsItem('https://myanimelist.net/news/4', 'Naruto special', '', null, '2026-05-01T10:00:00+00:00'),
        ]);
        $this->entries['entry-c'] = FollowedEntryFactory::entry('entry-c', 'Naruto', self::MAL_ID);

        $this->handle($this->message('entry-a', 'entry-c'));

        $this->assertSame(['entry-a'], array_map(
            static fn ($event) => $event->collectionEntryId,
            $this->eventBus->eventsOf(JikanFetchFailedEvent::class),
        ));
        $this->assertSame(['entry-c'], array_map(
            static fn ($event) => $event->collectionEntryId,
            $this->eventBus->eventsOf(JikanFetchSucceededEvent::class),
        ));
        $this->assertSame(['https://myanimelist.net/news/4'], $this->urlsOf('entry-c'));
    }

    private function handle(FetchJikanNewsMessage $message): void
    {
        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findById')->willReturnCallback(
            fn (string $entryId): ?CollectionEntry => $this->entries[$entryId] ?? null,
        );

        (new FetchJikanNewsHandler(
            $collectionRepository,
            $this->jikanNewsClient,
            new JikanArticleCollector($this->articleRepository, new MangaArticleMatcher()),
            $this->eventBus,
        ))($message);
    }

    private function message(string ...$entryIds): FetchJikanNewsMessage
    {
        return new FetchJikanNewsMessage(
            malId: self::MAL_ID,
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

    /** @return list<string> */
    private function urlsOf(string $entryId): array
    {
        return array_map(
            static fn (Article $article): string => $article->url,
            $this->articleRepository->articlesOf($entryId),
        );
    }
}
