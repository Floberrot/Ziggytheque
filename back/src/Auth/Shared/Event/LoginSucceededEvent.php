<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

use App\Shared\Domain\Event\SucceededEventInterface;

final readonly class LoginSucceededEvent implements SucceededEventInterface
{
    public function __construct(
        public string $correlationId,
        public string $userId,
        public string $email,
    ) {
    }
}
