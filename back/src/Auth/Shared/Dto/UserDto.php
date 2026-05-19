<?php

declare(strict_types=1);

namespace App\Auth\Shared\Dto;

final readonly class UserDto
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $role,
        public string $status,
        public string $notificationChannel,
        public ?string $notificationEmail,
        public ?string $discordWebhookUrl,
    ) {
    }
}
