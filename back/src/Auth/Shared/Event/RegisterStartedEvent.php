<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use App\Shared\Domain\Event\StartedEventInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RegisterStartedEvent implements StartedEventInterface
{
    public string $correlationId;
    public string $sourceName;

    public function __construct(
        ?string $correlationId = null,
    ) {
        $this->correlationId = $correlationId ?? Uuid::v4()->toRfc4122();
        $this->sourceName    = 'register';
    }
}
