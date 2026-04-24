<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Shared\Event\SchedulerFiredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogSchedulerFiredListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    public function __invoke(SchedulerFiredEvent $event): void
    {
        $log = new ActivityLog(
            id: $event->correlationId,
            eventType: EventTypeEnum::SchedulerFire,
            sourceName: 'scheduler',
        );
        $log->markSuccess(0, [
            'followed'        => $event->followedCount,
            'jobs_dispatched' => $event->jobsDispatched,
        ]);
        $this->activityLogRepository->save($log);
    }
}
