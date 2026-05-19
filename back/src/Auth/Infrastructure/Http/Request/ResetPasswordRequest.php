<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        public string $newPassword,
    ) {
    }
}
