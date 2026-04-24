<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class JikanFetchSucceededEvent
{
    public function __construct(
        public string $correlationId,
        public string $malId,
        public string $collectionEntryId,
        public int $newCount,
        public int $itemsReceived,
        public string $mangaTitle,
        public ?string $mangaCoverUrl,
    ) {
    }
}
