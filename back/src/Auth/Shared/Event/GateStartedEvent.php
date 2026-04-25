<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use Symfony\Component\Uid\Uuid;
use App\Shared\Domain\Event\StartedEventInterface;

final readonly class GateStartedEvent implements StartedEventInterface
{
    public string $correlationId;

    public function __construct(
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
    }
}
