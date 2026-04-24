<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Shared\Event\UserActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener]
final readonly class ActivityLogUserActionListener
{
    public function __construct(private ActivityLogRepositoryInterface $activityLogRepository)
    {
    }

    public function __invoke(UserActionEvent $event): void
    {
        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: EventTypeEnum::UserAction,
            sourceName: 'http',
            metadata: [
                'method'      => $event->method,
                'path'        => $event->path,
                'status_code' => $event->statusCode,
                'route'       => $event->routeName,
                'duration_ms' => $event->durationMs,
            ],
        );
        $log->markSuccess();
        $this->activityLogRepository->save($log);
    }
}
