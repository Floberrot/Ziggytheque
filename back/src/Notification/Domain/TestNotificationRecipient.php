<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Auth\Domain\User;

/**
 * Carrier for the data the test-notification handler needs to deliver a
 * test message and persist a fallback Notification entity on failure.
 *
 * Lives in Notification\Domain so the application handler stays free of any
 * Auth\Domain dependency: it only sees this VO and the channel string.
 */
final readonly class TestNotificationRecipient
{
    public function __construct(
        public User $user,
        public string $displayName,
        public string $channel,
        public ?string $notificationEmail,
        public ?string $discordWebhookUrl,
    ) {
    }
}
