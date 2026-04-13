<?php

declare(strict_types=1);

namespace App\Notification\Domain;

interface NotificationRepositoryInterface
{
    /** @return Notification[] */
    public function findUnread(): array;

    public function findById(string $id): ?Notification;

    public function save(Notification $notification): void;
}
