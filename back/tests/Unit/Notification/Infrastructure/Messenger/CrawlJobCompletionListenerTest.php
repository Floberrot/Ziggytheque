<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure\Messenger;

use App\Notification\Application\Discord\SendSchedulerDiscordSummaryMessage;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Domain\CrawlJobRepositoryInterface;
use App\Notification\Domain\FollowedSeries;
use App\Notification\Infrastructure\Messenger\CrawlJobCompletionListener;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/** Fan-in of a crawl run: the last job to finish asks for the Discord summary of the run. */
final class CrawlJobCompletionListenerTest extends TestCase
{
    /** @var list<array{string, string, bool}> */
    private array $completedJobs = [];

    /** @var list<object> */
    private array $dispatched = [];

    private ?DateTimeImmutable $runStartedAtWhenFinished = null;

    public function testAHandledFeedJobIsMarkedDone(): void
    {
        $this->listener()->onHandled(new WorkerMessageHandledEvent(new Envelope($this->rssJob()), 'async'));

        $this->assertSame([['rss-job', 'run-1', true]], $this->completedJobs);
        $this->assertSame([], $this->dispatched);
    }

    public function testTheLastJobOfTheRunAsksForTheSummary(): void
    {
        $this->runStartedAtWhenFinished = new DateTimeImmutable('2026-05-01 06:00:00');

        $this->listener()->onHandled(new WorkerMessageHandledEvent(new Envelope($this->jikanJob()), 'async'));

        $this->assertSame([['jikan-job', 'run-1', true]], $this->completedJobs);
        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(SendSchedulerDiscordSummaryMessage::class, $this->dispatched[0]);
        $this->assertSame($this->runStartedAtWhenFinished, $this->dispatched[0]->scheduledAt);
    }

    public function testAJobThatWillBeRetriedIsNotFinishedYet(): void
    {
        $event = new WorkerMessageFailedEvent(new Envelope($this->rssJob()), 'async', new RuntimeException('HTTP 503'));
        $event->setForRetry();

        $this->listener()->onFailed($event);

        $this->assertSame([], $this->completedJobs);
    }

    public function testAJobThatFailedForGoodStillCountsTowardsTheRun(): void
    {
        $this->runStartedAtWhenFinished = new DateTimeImmutable('2026-05-01 06:00:00');

        $this->listener()->onFailed(
            new WorkerMessageFailedEvent(new Envelope($this->rssJob()), 'async', new RuntimeException('HTTP 503')),
        );

        $this->assertSame([['rss-job', 'run-1', false]], $this->completedJobs);
        $this->assertCount(1, $this->dispatched);
    }

    public function testOtherMessagesAreNotCrawlJobs(): void
    {
        $this->listener()->onHandled(new WorkerMessageHandledEvent(new Envelope(new stdClass()), 'async'));

        $this->assertSame([], $this->completedJobs);
        $this->assertSame([], $this->dispatched);
    }

    private function listener(): CrawlJobCompletionListener
    {
        $test = $this;

        $crawlJobRepository = new class ($test) implements CrawlJobRepositoryInterface {
            public function __construct(private readonly CrawlJobCompletionListenerTest $test)
            {
            }

            public function createBatch(string $runId, array $jobIds): void
            {
            }

            public function completeAndTryFinishRun(string $jobId, string $runId, bool $success): ?DateTimeImmutable
            {
                return $this->test->recordCompletion($jobId, $runId, $success);
            }
        };

        $messageBus = new class ($test) implements MessageBusInterface {
            public function __construct(private readonly CrawlJobCompletionListenerTest $test)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->test->recordDispatch($message);

                return new Envelope($message, $stamps);
            }
        };

        return new CrawlJobCompletionListener($crawlJobRepository, $messageBus);
    }

    public function recordCompletion(string $jobId, string $runId, bool $success): ?DateTimeImmutable
    {
        $this->completedJobs[] = [$jobId, $runId, $success];

        return $this->runStartedAtWhenFinished;
    }

    public function recordDispatch(object $message): void
    {
        $this->dispatched[] = $message;
    }

    private function rssJob(): FetchRssFeedMessage
    {
        return new FetchRssFeedMessage(
            'manga-news',
            'https://news.example/feed',
            [new FollowedSeries('entry-a', 'One Piece')],
            'rss-job',
            'run-1',
        );
    }

    private function jikanJob(): FetchJikanNewsMessage
    {
        return new FetchJikanNewsMessage('13', [new FollowedSeries('entry-a', 'One Piece')], 'jikan-job', 'run-1');
    }
}
