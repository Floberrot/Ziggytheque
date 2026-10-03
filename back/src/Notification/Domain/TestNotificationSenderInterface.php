<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use App\Notification\Domain\Exception\TestNotificationConfigurationException;
use Throwable;

/** Delivers the one-off message a user sends themself to check their notification channel. */
interface TestNotificationSenderInterface
{
    /** @throws Throwable when the email cannot be sent */
    public function sendEmail(string $address, string $displayName): void;

    /**
     * @param string $webhookUrl already checked to be a discord.com webhook
     *
     * @throws TestNotificationConfigurationException when Discord refuses the message
     * @throws Throwable                              when Discord cannot be reached
     */
    public function sendDiscord(string $webhookUrl, string $displayName): void;
}
