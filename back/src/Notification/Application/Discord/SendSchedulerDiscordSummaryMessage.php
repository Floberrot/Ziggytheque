<?php

declare(strict_types=1);

namespace App\Notification\Application\Discord;

use DateTimeImmutable;

final readonly class SendSchedulerDiscordSummaryMessage
{
    public function __construct(
        public DateTimeImmutable $scheduledAt,
    ) {
    }
}
