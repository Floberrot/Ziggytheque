<?php

declare(strict_types=1);

namespace App\Notification\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\StartedEventInterface;

final readonly class JikanFetchStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $malId,
        public string $mangaTitle,
        public string $collectionEntryId,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
