<?php

declare(strict_types=1);

namespace App\Auth\Application\ResetPassword;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $token,
        public string $newPassword,
    ) {
    }
}
