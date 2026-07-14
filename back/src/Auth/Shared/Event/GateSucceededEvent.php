<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

/**
 * The issued JWT is deliberately NOT carried by this event: activity-log
 * metadata is built from event properties and must never contain credentials.
 */
final readonly class GateSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public ?string $userId = null,
    ) {
    }
}
