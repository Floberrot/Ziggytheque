<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumePrices;

use App\Manga\Domain\Marketplace;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\PriceOfferCacheInterface;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\Service\PriceOfferSorter;
use App\Manga\Domain\VolumePriceProviderInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetVolumePricesHandler
{
    public function __construct(
        private MangaRepositoryInterface $repository,
        private VolumePriceProviderInterface $priceProvider,
        private PriceOfferCacheInterface $cache,
        private PriceOfferSorter $sorter,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetVolumePricesQuery $query): array
    {
        $manga = $this->repository->findById($query->mangaId);
        if ($manga === null) {
            throw new NotFoundException('Manga', $query->mangaId);
        }

        $volume = null;
        foreach ($manga->volumes as $candidate) {
            if ($candidate->id === $query->volumeId) {
                $volume = $candidate;
                break;
            }
        }

        if ($volume === null) {
            throw new NotFoundException('Volume', $query->volumeId);
        }

        if ($volume->isbn === null) {
            return ['offers' => [], 'hasIsbn' => false, 'marketplace' => null];
        }

        $marketplace = $query->marketplace !== null
            ? Marketplace::fromValue($query->marketplace)
            : Marketplace::fromLanguage($manga->language);

        $cachedOffers = $this->cache->get($volume->isbn, $marketplace);
        if ($cachedOffers !== null) {
            return ['offers' => $cachedOffers, 'hasIsbn' => true, 'marketplace' => $marketplace->value];
        }

        $offers = $this->sorter->sort($this->priceProvider->findOffers($volume->isbn, $marketplace));

        $offersArray = array_map(
            static fn (PriceOfferDto $offer) => $offer->toArray(),
            $offers,
        );

        $this->cache->put($volume->isbn, $marketplace, $offersArray);

        return ['offers' => $offersArray, 'hasIsbn' => true, 'marketplace' => $marketplace->value];
    }
}
