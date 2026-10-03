<?php

declare(strict_types=1);

namespace App\Notification\Application\GetUnreadNotifications;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUnreadNotificationsHandler
{
    public function __construct(private NotificationRepositoryInterface $repository)
    {
    }

    /** @return list<array<string, mixed>> newest first */
    public function __invoke(GetUnreadNotificationsQuery $query): array
    {
        return array_map(
            static fn (Notification $notification): array => $notification->toArray(),
            $this->repository->findUnread(),
        );
    }
}
