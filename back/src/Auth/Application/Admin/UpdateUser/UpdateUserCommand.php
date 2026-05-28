<?php

declare(strict_types=1);

namespace App\Auth\Application\Admin\UpdateUser;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\UserStatusEnum;

final readonly class UpdateUserCommand
{
    public function __construct(
        public string $userId,
        public ?string $displayName,
        public ?UserStatusEnum $status,
        public ?NotificationChannelEnum $notificationChannel,
    ) {
    }
}
