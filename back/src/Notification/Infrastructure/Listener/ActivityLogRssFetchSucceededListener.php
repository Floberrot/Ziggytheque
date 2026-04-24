<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Shared\Event\RssFetchSucceededEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogRssFetchSucceededListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    public function __invoke(RssFetchSucceededEvent $event): void
    {
        $log = $this->activityLogRepository->findById($event->correlationId);
        if ($log === null) {
            return;
        }
        $log->markSuccess($event->newCount, ['items_scanned' => $event->itemsScanned]);
        $this->activityLogRepository->save($log);
    }
}
