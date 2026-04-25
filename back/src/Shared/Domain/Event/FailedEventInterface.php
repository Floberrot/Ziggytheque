<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Marker interface for domain events that represent a failed operation.
 * Implementors must expose: public readonly string $correlationId, $error, $exceptionClass.
 */
interface FailedEventInterface
{
}
