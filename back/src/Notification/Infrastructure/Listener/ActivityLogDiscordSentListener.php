<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Shared\Event\DiscordNotificationSentEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogDiscordSentListener
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    public function __invoke(DiscordNotificationSentEvent $event): void
    {
        $entry = $this->collectionRepository->findById($event->collectionEntryId);

        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::DiscordSent,
            sourceName: 'discord',
            collectionEntry: $entry,
        );
        $log->markSuccess($event->articleCount, ['article_count' => $event->articleCount]);
        $this->activityLogRepository->save($log);
    }
}
