<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class DiscordNotificationSentEvent
{
    /**
     * @param array<int, array<string, mixed>> $articles
     */
    public function __construct(
        public string $correlationId,
        public string $collectionEntryId,
        public string $mangaTitle,
        public ?string $mangaCoverUrl,
        public int $articleCount,
        public array $articles,
    ) {
    }
}
