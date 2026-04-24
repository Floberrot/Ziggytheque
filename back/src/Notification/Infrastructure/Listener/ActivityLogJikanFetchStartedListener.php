<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Shared\Event\JikanFetchStartedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogJikanFetchStartedListener
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    public function __invoke(JikanFetchStartedEvent $event): void
    {
        $entry = $this->collectionRepository->findById($event->collectionEntryId);

        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::JikanFetch,
            sourceName: 'jikan-news',
            collectionEntry: $entry,
            metadata: ['mal_id' => $event->malId, 'manga' => $event->mangaTitle],
        );
        $this->activityLogRepository->save($log);
    }
}
