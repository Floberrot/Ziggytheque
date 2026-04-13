<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddToCollectionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $mangaId,
    ) {
    }
}
