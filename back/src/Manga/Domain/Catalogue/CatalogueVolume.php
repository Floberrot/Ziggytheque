<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

use App\Manga\Domain\Isbn;

final readonly class CatalogueVolume
{
    public function __construct(
        public int $number,
        public ?Isbn $isbn,
        public ?string $coverUrl,
    ) {
    }

    /** @return array{number: int, isbn: string|null, coverUrl: string|null} */
    public function toArray(): array
    {
        return [
            'number'   => $this->number,
            'isbn'     => $this->isbn?->value,
            'coverUrl' => $this->coverUrl,
        ];
    }
}
