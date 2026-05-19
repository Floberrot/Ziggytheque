<?php

declare(strict_types=1);

namespace App\Auth\Application\VerifyEmail;

final readonly class VerifyEmailCommand
{
    public function __construct(public string $token)
    {
    }
}
