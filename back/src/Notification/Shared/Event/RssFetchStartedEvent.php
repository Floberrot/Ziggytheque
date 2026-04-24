<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use Symfony\Component\Uid\Uuid;

final readonly class RssFetchStartedEvent
{
    public string $correlationId;

    public function __construct(
        public string $feedName,
        public string $feedUrl,
        public string $mangaTitle,
        public string $collectionEntryId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
