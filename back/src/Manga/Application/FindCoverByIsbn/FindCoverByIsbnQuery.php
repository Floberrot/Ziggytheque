<?php

declare(strict_types=1);

namespace App\Manga\Application\FindCoverByIsbn;

final readonly class FindCoverByIsbnQuery
{
    public function __construct(
        public string $isbn,
    ) {
    }
}
