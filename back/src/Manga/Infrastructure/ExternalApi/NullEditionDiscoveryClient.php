<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;

final readonly class NullEditionDiscoveryClient implements EditionDiscoveryInterface
{
    /**
     * @return ExternalEditionDto[]
     */
    public function discoverEditions(string $workTitle, Country $country): array
    {
        return [];
    }
}
