<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use App\Auth\Domain\NotificationChannelEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateNotificationPreferencesRequest
{
    public function __construct(
        #[Assert\NotNull]
        public NotificationChannelEnum $channel,
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $notificationEmail = null,
        #[Assert\Length(max: 500)]
        public ?string $discordWebhookUrl = null,
    ) {
    }
}
