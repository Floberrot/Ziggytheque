<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Http;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogOwnerResolverInterface;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Shared\Event\UserActionEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener]
final readonly class ActivityLogUserActionListener
{
    public function __construct(
        private ActivityLogRepositoryInterface $activityLogRepository,
        private ActivityLogOwnerResolverInterface $ownerResolver,
    ) {
    }

    public function __invoke(UserActionEvent $event): void
    {
        $isHttpError = $event->statusCode >= 400;

        $log = new ActivityLog(
            id: Uuid::v4()->toRfc4122(),
            eventType: $isHttpError ? EventTypeEnum::HttpError : EventTypeEnum::UserAction,
            sourceName: 'http',
            owner: $this->ownerResolver->currentOwner(),
            metadata: [
                'method'      => $event->method,
                'path'        => $event->path,
                'status_code' => $event->statusCode,
                'route'       => $event->routeName,
                'duration_ms' => $event->durationMs,
                'ip'          => $event->clientIp,
                'user_agent'  => $event->userAgent,
            ],
        );

        if ($isHttpError) {
            $log->markError(sprintf('HTTP %d %s %s', $event->statusCode, $event->method, $event->path));
        } else {
            $log->markSuccess();
        }

        $this->activityLogRepository->save($log);
    }
}
