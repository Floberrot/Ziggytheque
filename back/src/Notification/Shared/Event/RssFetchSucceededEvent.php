<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

final readonly class RssFetchSucceededEvent
{
    public function __construct(
        public string $correlationId,
        public string $feedName,
        public string $collectionEntryId,
        public int $newCount,
        public int $itemsScanned,
        public string $mangaTitle,
        public ?string $mangaCoverUrl,
    ) {
    }
}
