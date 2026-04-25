<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use App\Shared\Domain\Event\FailedEventInterface;

final readonly class GateFailedEvent implements FailedEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $error,
        public string $exceptionClass,
    ) {
    }
}
