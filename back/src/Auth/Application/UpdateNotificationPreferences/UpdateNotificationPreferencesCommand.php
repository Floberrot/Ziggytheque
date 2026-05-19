<?php

declare(strict_types=1);

namespace App\Auth\Application\UpdateNotificationPreferences;

use App\Auth\Domain\NotificationChannelEnum;

final readonly class UpdateNotificationPreferencesCommand
{
    public function __construct(
        public string $userId,
        public NotificationChannelEnum $channel,
        public ?string $notificationEmail,
        public ?string $discordWebhookUrl,
    ) {
    }
}
