<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\VolumePriceProviderInterface;

final readonly class NullPriceProvider implements VolumePriceProviderInterface
{
    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        return [];
    }
}
