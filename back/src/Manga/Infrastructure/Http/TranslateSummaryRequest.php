<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TranslateSummaryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $text,
    ) {
    }
}
