<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use Closure;

/**
 * A price source that sends its request without waiting for the answer (HttpClient
 * responses are lazy), so {@see CompositePriceProvider} starts every source before it
 * reads any of them: the sources answer concurrently instead of one after another.
 */
interface DeferredPriceProviderInterface extends VolumePriceProviderInterface
{
    /**
     * Sends the request(s) and returns at once. The returned closure waits for the answer
     * and reads it; it never throws — a failing source yields no offer.
     *
     * @return Closure(): list<PriceOfferDto>
     */
    public function requestOffers(Isbn $isbn, Marketplace $marketplace): Closure;
}
