<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class CompositePriceProvider implements VolumePriceProviderInterface
{
    /** @param iterable<VolumePriceProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private LoggerInterface $logger,
    ) {
    }

    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        /** @var list<PriceOfferDto> $offers */
        $offers = [];

        foreach ($this->providers as $provider) {
            try {
                $providerOffers = $provider->findOffers($isbn, $marketplace);

                $this->logger->info('COMPOSITE PRICES : source result.', [
                    'provider'    => $provider::class,
                    'count'       => count($providerOffers),
                    'marketplace' => $marketplace->value,
                ]);

                foreach ($providerOffers as $offer) {
                    $offers[] = $offer;
                }
            } catch (Throwable $exception) {
                $this->logger->error('COMPOSITE PRICES : provider failed, skipping.', [
                    'provider' => $provider::class,
                    'error'    => $exception->getMessage(),
                ]);
            }
        }

        return $offers;
    }
}
