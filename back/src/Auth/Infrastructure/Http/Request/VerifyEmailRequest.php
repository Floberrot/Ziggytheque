<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyEmailRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
