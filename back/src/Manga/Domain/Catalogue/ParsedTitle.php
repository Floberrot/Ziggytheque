<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

final readonly class ParsedTitle
{
    public function __construct(
        public string $workTitle,
        public ?string $headQualifier,
        public ?int $volumeNumber,
        public ?string $trailingQualifier,
    ) {
    }
}
