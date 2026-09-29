<?php

declare(strict_types=1);

namespace App\Collection\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ScanIsbnStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        public string $isbn,
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
