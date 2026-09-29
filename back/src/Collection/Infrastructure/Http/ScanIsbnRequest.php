<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScanIsbnRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public string $isbn = '',
    ) {
    }
}
