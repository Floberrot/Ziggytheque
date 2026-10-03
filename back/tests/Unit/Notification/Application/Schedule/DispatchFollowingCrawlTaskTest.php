<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\Schedule;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Application\Schedule\DispatchFollowingCrawlTask;
use App\Notification\Domain\CrawlJobRepositoryInterface;
use App\Notification\Domain\CrawlRunRepositoryInterface;
use App\Notification\Domain\FollowedSeries;
use App\Notification\Domain\Service\CrawlPlanner;
use App\Notification\Shared\Event\SchedulerFiredEvent;
use App\Tests\Doubles\Notification\FollowedEntryFactory;
use App\Tests\Doubles\Shared\RecordingEventBus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

final class DispatchFollowingCrawlTaskTest extends TestCase
{
    private const array FEEDS = [
        ['name' => 'manga-news', 'url' => 'https://manga-news.example/feed'],
        ['name' => 'animeland', 'url' => 'https://animeland.example/feed'],
    ];

    /** @var list<object> */
    private array $dispatched = [];

    /** @var list<array{string, DateTimeImmutable}> */
    private array $createdRuns = [];

    /** @var list<array{string, list<string>}> */
    private array $createdJobBatches = [];

    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    public function testDownloadsEachFeedOnceForEveryFollowedSeries(): void
    {
        $this->dispatchCrawl([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'Berserk'),
        ]);

        $feedJobs = $this->dispatchedOf(FetchRssFeedMessage::class);
        $this->assertSame(['manga-news', 'animeland'], array_map(static fn ($job) => $job->feedName, $feedJobs));
        $this->assertSame('https://manga-news.example/feed', $feedJobs[0]->feedUrl);
        $expectedSeries = [new FollowedSeries('entry-a', 'One Piece'), new FollowedSeries('entry-b', 'Berserk')];
        $this->assertEquals($expectedSeries, $feedJobs[0]->followedSeries);
        $this->assertEquals($expectedSeries, $feedJobs[1]->followedSeries);
    }

    /** Two accounts follow their own copy of One Piece: its Jikan news are fetched once. */
    public function testAsksJikanOnceForEachFollowedMyAnimeListSeries(): void
    {
        $this->dispatchCrawl([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'Berserk'),
            FollowedEntryFactory::entry('entry-c', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-d', 'Naruto', '11'),
        ]);

        $jikanJobs = $this->dispatchedOf(FetchJikanNewsMessage::class);
        $this->assertSame(['13', '11'], array_map(static fn ($job) => $job->malId, $jikanJobs));
        $this->assertEquals(
            [new FollowedSeries('entry-a', 'One Piece'), new FollowedSeries('entry-c', 'One Piece')],
            $jikanJobs[0]->followedSeries,
        );
    }

    public function testRecordsTheRunAndOneJobPerMessage(): void
    {
        $this->dispatchCrawl([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'One Piece', '13'),
        ]);

        // 2 feeds + 1 MyAnimeList series, whatever the number of followed copies.
        $this->assertCount(3, $this->dispatched);
        $this->assertCount(1, $this->createdRuns);
        [$runId] = $this->createdRuns[0];

        $this->assertCount(1, $this->createdJobBatches);
        [$batchRunId, $jobIds] = $this->createdJobBatches[0];
        $this->assertSame($runId, $batchRunId);

        $jobs = $this->dispatchedOf(FetchRssFeedMessage::class, FetchJikanNewsMessage::class);
        $this->assertCount(3, $jobs);
        $this->assertSame(
            $jobIds,
            array_map(static fn (FetchRssFeedMessage|FetchJikanNewsMessage $job): string => $job->crawlJobId, $jobs),
        );
        $this->assertCount(3, array_unique($jobIds));
        foreach ($jobs as $job) {
            $this->assertSame($runId, $job->crawlRunId);
        }
    }

    public function testTellsTheJournalWhatWasDispatched(): void
    {
        $this->dispatchCrawl([
            FollowedEntryFactory::entry('entry-a', 'One Piece', '13'),
            FollowedEntryFactory::entry('entry-b', 'Berserk'),
        ]);

        $fired = $this->eventBus->eventsOf(SchedulerFiredEvent::class);
        $this->assertCount(1, $fired);
        $this->assertSame(2, $fired[0]->followedCount);
        $this->assertSame(3, $fired[0]->jobsDispatched);
    }

    public function testNothingFollowedMeansNoRun(): void
    {
        $this->dispatchCrawl([]);

        $this->assertSame([], $this->dispatched);
        $this->assertSame([], $this->createdRuns);
        $this->assertSame([], $this->eventBus->events);
    }

    public function testNoSourceMeansNoRun(): void
    {
        $this->dispatchCrawl([FollowedEntryFactory::entry('entry-b', 'Berserk')], feeds: []);

        $this->assertSame([], $this->dispatched);
        $this->assertSame([], $this->createdRuns);
    }

    public function testRunsEveryDayAtSixUtc(): void
    {
        $attributes = (new ReflectionClass(DispatchFollowingCrawlTask::class))->getAttributes(AsCronTask::class);

        $this->assertSame('0 6 * * *', $attributes[0]->newInstance()->expression);
    }

    /**
     * @param list<CollectionEntry>                     $followedEntries
     * @param array<int, array{name: string, url: string}> $feeds
     */
    private function dispatchCrawl(array $followedEntries, array $feeds = self::FEEDS): void
    {
        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findFollowed')->willReturn($followedEntries);

        $test = $this;

        $crawlRunRepository = new class ($test) implements CrawlRunRepositoryInterface {
            public function __construct(private readonly DispatchFollowingCrawlTaskTest $test)
            {
            }

            public function create(string $id, DateTimeImmutable $startedAt): void
            {
                $this->test->recordRun($id, $startedAt);
            }
        };

        $crawlJobRepository = new class ($test) implements CrawlJobRepositoryInterface {
            public function __construct(private readonly DispatchFollowingCrawlTaskTest $test)
            {
            }

            public function createBatch(string $runId, array $jobIds): void
            {
                $this->test->recordJobBatch($runId, array_values($jobIds));
            }

            public function completeAndTryFinishRun(string $jobId, string $runId, bool $success): ?DateTimeImmutable
            {
                return null;
            }
        };

        $messageBus = new class ($test) implements MessageBusInterface {
            public function __construct(private readonly DispatchFollowingCrawlTaskTest $test)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->test->recordDispatch($message);

                return new Envelope($message, $stamps);
            }
        };

        (new DispatchFollowingCrawlTask(
            $collectionRepository,
            $crawlRunRepository,
            $crawlJobRepository,
            new CrawlPlanner(),
            $messageBus,
            $this->eventBus,
            $feeds,
        ))();
    }

    public function recordRun(string $runId, DateTimeImmutable $startedAt): void
    {
        $this->createdRuns[] = [$runId, $startedAt];
    }

    /** @param list<string> $jobIds */
    public function recordJobBatch(string $runId, array $jobIds): void
    {
        $this->createdJobBatches[] = [$runId, $jobIds];
    }

    public function recordDispatch(object $message): void
    {
        $this->dispatched[] = $message;
    }

    /**
     * @template T of object
     * @param  class-string<T> ...$messageClasses
     * @return list<T>
     */
    private function dispatchedOf(string ...$messageClasses): array
    {
        return array_values(array_filter(
            $this->dispatched,
            static fn (object $message): bool => in_array($message::class, $messageClasses, true),
        ));
    }
}
