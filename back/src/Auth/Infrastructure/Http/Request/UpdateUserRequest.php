<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use App\Auth\Domain\NotificationChannelEnum;
use App\Auth\Domain\UserStatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 100)]
        public ?string $displayName = null,
        public ?UserStatusEnum $status = null,
        public ?NotificationChannelEnum $notificationChannel = null,
    ) {
    }
}
