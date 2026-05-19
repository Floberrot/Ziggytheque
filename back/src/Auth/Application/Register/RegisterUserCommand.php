<?php

declare(strict_types=1);

namespace App\Auth\Application\Register;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $displayName,
    ) {
    }
}
