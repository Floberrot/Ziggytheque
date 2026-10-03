<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Domain\CrawlJobRepositoryInterface;
use App\Notification\Domain\CrawlRunRepositoryInterface;
use App\Notification\Domain\Service\CrawlPlanner;
use App\Notification\Shared\Event\SchedulerFiredEvent;
use App\Shared\Application\Bus\EventBusInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

/**
 * Runs once a day at 06:00 UTC (= 07:00 CET / 08:00 CEST).
 * Dispatches async crawl jobs, each source downloaded once: one per RSS feed (matched
 * against every followed series) and one per MyAnimeList series that is followed.
 */
#[AsCronTask('0 6 * * *')]
final readonly class DispatchFollowingCrawlTask
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private CrawlRunRepositoryInterface $crawlRunRepository,
        private CrawlJobRepositoryInterface $crawlJobRepository,
        private CrawlPlanner $crawlPlanner,
        private MessageBusInterface $messageBus,
        private EventBusInterface $eventBus,
        /** @var array<int, array{name: string, url: string}> */
        private array $rssFeeds,
    ) {
    }

    public function __invoke(): void
    {
        $followed       = $this->collectionRepository->findFollowed();
        $followedSeries = $this->crawlPlanner->followedSeries($followed);

        if ($followedSeries === []) {
            return;
        }

        /** @var list<FetchRssFeedMessage|FetchJikanNewsMessage> $jobs */
        $jobs  = [];
        $runId = Uuid::v4()->toRfc4122();

        foreach ($this->rssFeeds as $feed) {
            $jobs[] = new FetchRssFeedMessage(
                feedName: $feed['name'],
                feedUrl: $feed['url'],
                followedSeries: $followedSeries,
                crawlJobId: Uuid::v4()->toRfc4122(),
                crawlRunId: $runId,
            );
        }

        foreach ($this->crawlPlanner->followedSeriesByMalId($followed) as $malSeries) {
            $jobs[] = new FetchJikanNewsMessage(
                malId: $malSeries['malId'],
                followedSeries: $malSeries['followedSeries'],
                crawlJobId: Uuid::v4()->toRfc4122(),
                crawlRunId: $runId,
            );
        }

        if ($jobs === []) {
            return;
        }

        $this->crawlRunRepository->create($runId, new DateTimeImmutable());
        $this->crawlJobRepository->createBatch(
            $runId,
            array_map(static fn (FetchRssFeedMessage|FetchJikanNewsMessage $job): string => $job->crawlJobId, $jobs),
        );

        foreach ($jobs as $message) {
            $this->messageBus->dispatch($message);
        }

        $this->eventBus->publish(new SchedulerFiredEvent(
            followedCount: count($followed),
            jobsDispatched: count($jobs),
        ));
    }
}
