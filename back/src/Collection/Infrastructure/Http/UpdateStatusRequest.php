<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStatusRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['not_started', 'in_progress', 'completed', 'on_hold', 'dropped'])]
        public string $status,
    ) {
    }
}
