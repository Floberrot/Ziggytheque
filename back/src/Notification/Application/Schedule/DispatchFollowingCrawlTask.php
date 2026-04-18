<?php

declare(strict_types=1);

namespace App\Notification\Application\Schedule;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Application\Fetch\FetchJikanNewsMessage;
use App\Notification\Application\Fetch\FetchRssFeedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Runs at 07:00 and 19:00 every day.
 * Dispatches async crawl jobs (one per source per followed manga).
 */
#[AsCronTask('* * * * *')]
final readonly class DispatchFollowingCrawlTask
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        /** @var array<int, array{name: string, url: string}> */
        private array $rssFeeds,
    ) {}

    public function __invoke(): void
    {
        $followed = $this->collectionRepository->findFollowed();

        if (empty($followed)) {
            $this->logger->info('FollowingCrawl: no followed entries, skipping.');
            return;
        }

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

        $this->logger->info('FollowingCrawl dispatched', [
            'followed' => count($followed),
            'jobs'     => $totalJobs,
        ]);
    }
}
