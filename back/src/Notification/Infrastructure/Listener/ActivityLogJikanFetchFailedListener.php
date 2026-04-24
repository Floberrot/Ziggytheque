<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Listener;

use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Shared\Event\JikanFetchFailedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ActivityLogJikanFetchFailedListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    public function __invoke(JikanFetchFailedEvent $event): void
    {
        $log = $this->activityLogRepository->findById($event->correlationId);
        if ($log === null) {
            return;
        }
        $log->markError($event->error, ['exception_class' => $event->exceptionClass]);
        $this->activityLogRepository->save($log);
    }
}
