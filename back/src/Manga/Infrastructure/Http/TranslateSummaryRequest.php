<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TranslateSummaryRequest
{
    public const int MAX_LENGTH = 5000;

    public function __construct(
        #[Assert\NotBlank]
        // Sent in the translator's query string: a longer text would not fit the URL.
        #[Assert\Length(max: self::MAX_LENGTH)]
        public string $text,
    ) {
    }
}
