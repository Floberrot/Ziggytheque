<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestResetRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email,
    ) {
    }
}
