<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/** Both ids target one tome; none opens a free session for the "add a manga" page. */
final readonly class CreateScanSessionRequest
{
    public function __construct(
        #[Assert\Length(max: 36)]
        public ?string $mangaId = null,
        #[Assert\Length(max: 36)]
        public ?string $volumeId = null,
    ) {
    }
}
