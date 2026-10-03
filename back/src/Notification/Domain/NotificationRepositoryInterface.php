<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface NotificationRepositoryInterface
{
    /** @return list<Notification> the current account's unread notifications, newest first */
    public function findUnread(): array;

    public function save(Notification $notification): void;
}
