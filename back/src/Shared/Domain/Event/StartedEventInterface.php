<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Marker interface for domain events that represent the start of an operation.
 * Implementors must expose a public readonly string $correlationId property.
 */
interface StartedEventInterface
{
}
