<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Shared\Event\JikanFetchSucceededEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogJikanFetchSucceededListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    public function __invoke(JikanFetchSucceededEvent $event): void
    {
        $log = $this->activityLogRepository->findById($event->correlationId);
        if ($log === null) {
            return;
        }
        $log->markSuccess($event->newCount, ['items_received' => $event->itemsReceived]);
        $this->activityLogRepository->save($log);
    }
}
