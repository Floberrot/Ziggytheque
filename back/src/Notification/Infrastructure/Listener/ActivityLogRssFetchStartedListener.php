<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Shared\Event\RssFetchStartedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogRssFetchStartedListener
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    public function __invoke(RssFetchStartedEvent $event): void
    {
        $entry = $this->collectionRepository->findById($event->collectionEntryId);

        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::RssFetch,
            sourceName: $event->feedName,
            collectionEntry: $entry,
            metadata: ['feed_url' => $event->feedUrl, 'manga' => $event->mangaTitle],
        );
        $this->activityLogRepository->save($log);
    }
}
