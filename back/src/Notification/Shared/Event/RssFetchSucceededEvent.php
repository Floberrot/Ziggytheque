<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class RssFetchSucceededEvent implements SucceededEventInterface
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
