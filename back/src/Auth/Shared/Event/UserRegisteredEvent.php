<?php

declare(strict_types=1);

namespace App\Auth\Shared\Event;

final readonly class UserRegisteredEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $displayName,
        public string $verificationTokenPlain,
    ) {
    }
}
