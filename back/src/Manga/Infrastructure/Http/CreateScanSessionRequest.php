<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateScanSessionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $mangaId = '',
        #[Assert\NotBlank]
        public string $volumeId = '',
    ) {
    }
}
