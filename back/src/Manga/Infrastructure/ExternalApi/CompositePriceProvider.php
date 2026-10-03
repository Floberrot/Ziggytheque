<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class CompositePriceProvider implements VolumePriceProviderInterface
{
    /** @param iterable<VolumePriceProviderInterface> $providers highest priority first */
    public function __construct(
        private iterable $providers,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Every source's request is sent first, then the answers are read in priority order:
     * the sources answer concurrently, the offers keep the same order, and a source that
     * fails or times out only loses its own offers.
     */
    public function findOffers(Isbn $isbn, Marketplace $marketplace): array
    {
        /** @var list<array{VolumePriceProviderInterface, Closure(): list<PriceOfferDto>}> $pendingSources */
        $pendingSources = [];
        foreach ($this->providers as $provider) {
            $pendingSources[] = [$provider, $this->start($provider, $isbn, $marketplace)];
        }

        /** @var list<PriceOfferDto> $offers */
        $offers = [];

        foreach ($pendingSources as [$provider, $readOffers]) {
            try {
                $providerOffers = $readOffers();

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

    /**
     * Starts one source. A source that cannot send its request on its own is simply asked
     * when its turn to be read comes.
     *
     * @return Closure(): list<PriceOfferDto>
     */
    private function start(VolumePriceProviderInterface $provider, Isbn $isbn, Marketplace $marketplace): Closure
    {
        if (!$provider instanceof DeferredPriceProviderInterface) {
            return static fn (): array => $provider->findOffers($isbn, $marketplace);
        }

        try {
            return $provider->requestOffers($isbn, $marketplace);
        } catch (Throwable $exception) {
            // Reported with the other results, in the source's own turn.
            return static fn (): array => throw $exception;
        }
    }
}
