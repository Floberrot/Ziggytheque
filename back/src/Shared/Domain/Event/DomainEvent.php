<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use Symfony\Component\Uid\Uuid;

abstract readonly class DomainEvent
{
    final public function __construct()
    {
        // Base domain event (no state). Subclasses define readonly properties via constructor params.
    }
}
