<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 255)]
        public string $password,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $displayName,
    ) {
    }
}
