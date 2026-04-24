<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use App\Notification\Shared\Event\SchedulerFiredEvent;
use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Runs at 07:00 and 19:00 every day.
 * Dispatches async crawl jobs (one per source per followed manga).
 */
#[AsCronTask('0 7,19 * * *')]
final readonly class DispatchFollowingCrawlTask
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MessageBusInterface $messageBus,
        private EventBusInterface $eventBus,
        /** @var array<int, array{name: string, url: string}> */
        private array $rssFeeds,
    ) {
    }

    public function __invoke(): void
    {
        $followed  = $this->collectionRepository->findFollowed();
        $totalJobs = 0;

        foreach ($followed as $entry) {
            foreach ($this->rssFeeds as $feed) {
                $this->messageBus->dispatch(new FetchRssFeedMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    feedName: $feed['name'],
                    feedUrl: $feed['url'],
                ));
                ++$totalJobs;
            }

            if ($entry->manga->externalId !== null) {
                $this->messageBus->dispatch(new FetchJikanNewsMessage(
                    collectionEntryId: $entry->id,
                    mangaTitle: $entry->manga->title,
                    malId: $entry->manga->externalId,
                ));
                ++$totalJobs;
            }
        }

        $this->eventBus->publish(new SchedulerFiredEvent(
            followedCount: count($followed),
            jobsDispatched: $totalJobs,
        ));
    }
}
