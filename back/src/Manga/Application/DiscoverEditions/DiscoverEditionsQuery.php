<?php

declare(strict_types=1);

namespace App\Manga\Application\DiscoverEditions;

use App\Manga\Domain\Country;

final readonly class DiscoverEditionsQuery
{
    public function __construct(
        public string $title,
        public Country $country = Country::France,
    ) {
    }
}
