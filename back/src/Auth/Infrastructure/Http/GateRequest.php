<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $password,
    ) {
    }
}
