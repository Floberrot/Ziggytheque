<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitScanRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $scanToken = '',
        #[Assert\NotBlank]
        public string $isbn = '',
    ) {
    }
}
