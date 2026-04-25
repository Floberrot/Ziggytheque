<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Domain\CrawlJobRepositoryInterface;
use App\Notification\Domain\CrawlRunRepositoryInterface;
use App\Notification\Shared\Event\SchedulerFiredEvent;
use App\Shared\Application\Bus\EventBusInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Symfony\Component\Uid\Uuid;

/**
 * Runs once a day at 06:00 UTC (= 07:00 CET / 08:00 CEST).
 * Dispatches async crawl jobs (one per source per followed manga).
 */
#[AsCronTask('0 6 * * *')]
final readonly class DispatchFollowingCrawlTask
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private CrawlRunRepositoryInterface $crawlRunRepository,
        private CrawlJobRepositoryInterface $crawlJobRepository,
        private MessageBusInterface $messageBus,
        private EventBusInterface $eventBus,
        /** @var array<int, array{name: string, url: string}> */
        private array $rssFeeds,
    ) {
    }

    public function __invoke(): void
    {
        $followed = $this->collectionRepository->findFollowed();

        /** @var array<int, FetchRssFeedMessage|FetchJikanNewsMessage> $jobs */
        $jobs   = [];
        $runId  = Uuid::v4()->toRfc4122();
        $jobIds = [];

        foreach ($followed as $entry) {
            foreach ($this->rssFeeds as $feed) {
                $jobId    = Uuid::v4()->toRfc4122();
                $jobIds[] = $jobId;
                $jobs[]   = new FetchRssFeedMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    feedName: $feed['name'],
                    feedUrl: $feed['url'],
                    crawlJobId: $jobId,
                    crawlRunId: $runId,
                );
            }

            if ($entry->manga->externalId !== null) {
                $jobId    = Uuid::v4()->toRfc4122();
                $jobIds[] = $jobId;
                $jobs[]   = new FetchJikanNewsMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    malId: $entry->manga->externalId,
                    crawlJobId: $jobId,
                    crawlRunId: $runId,
                );
            }
        }

        if ($jobs === []) {
            return;
        }

        $this->crawlRunRepository->create($runId, new DateTimeImmutable());
        $this->crawlJobRepository->createBatch($runId, $jobIds);

        foreach ($jobs as $message) {
            $this->messageBus->dispatch($message);
        }

        $this->eventBus->publish(new SchedulerFiredEvent(
            followedCount: count($followed),
            jobsDispatched: count($jobs),
        ));
    }
}
