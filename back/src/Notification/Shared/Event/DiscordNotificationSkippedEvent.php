<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class DiscordNotificationSkippedEvent
{
    public function __construct(
        public string $collectionEntryId,
        public string $mangaTitle,
        public string $reason,
    ) {
    }
}
