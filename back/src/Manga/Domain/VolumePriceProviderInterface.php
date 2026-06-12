<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface VolumePriceProviderInterface
{
    /**
     * Returns every price offer found for the given ISBN on the given marketplace.
     *
     * @return list<PriceOfferDto>
     */
    public function findOffers(Isbn $isbn, Marketplace $marketplace): array;
}
