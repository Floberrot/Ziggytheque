<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\GetVolumePrices;

use App\Manga\Application\GetVolumePrices\GetVolumePricesHandler;
use App\Manga\Application\GetVolumePrices\GetVolumePricesQuery;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferCacheInterface;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\Service\PriceOfferSorter;
use App\Manga\Domain\Service\RetailerOfferResolver;
use App\Manga\Domain\Volume;
use App\Manga\Domain\VolumePriceProviderInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetVolumePricesHandlerTest extends TestCase
{
    private const string MANGA_ID  = 'manga-1';
    private const string VOLUME_ID = 'volume-1';
    private const string ISBN      = '9782723425483';

    private MangaRepositoryInterface&MockObject $repository;
    private VolumePriceProviderInterface&MockObject $priceProvider;
    private PriceOfferCacheInterface&MockObject $cache;
    private GetVolumePricesHandler $handler;

    protected function setUp(): void
    {
        $this->repository    = $this->createMock(MangaRepositoryInterface::class);
        $this->priceProvider = $this->createMock(VolumePriceProviderInterface::class);
        $this->cache         = $this->createMock(PriceOfferCacheInterface::class);

        $this->handler = new GetVolumePricesHandler(
            $this->repository,
            $this->priceProvider,
            $this->cache,
            new PriceOfferSorter(),
            new RetailerOfferResolver(),
        );
    }

    private function makeManga(?Isbn $isbn): Manga
    {
        $manga  = new Manga(id: self::MANGA_ID, title: 'Berserk', edition: null, language: 'fr');
        $volume = new Volume(id: self::VOLUME_ID, manga: $manga, number: 1, isbn: $isbn);
        $manga->addVolume($volume);

        return $manga;
    }

    private function makeOffer(string $merchant, float $amount): PriceOfferDto
    {
        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     $merchant,
            merchantLogo: 'test',
            amount:       $amount,
            currency:     'EUR',
            url:          null,
            imageUrl:     null,
            source:       'test',
        );
    }

    public function testThrowsNotFoundForUnknownManga(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID));
    }

    public function testThrowsNotFoundForUnknownVolume(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(null));

        $this->expectException(NotFoundException::class);

        ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, 'other-volume'));
    }

    public function testVolumeWithoutIsbnReturnsThreeNotFoundRetailers(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(null));

        $result = ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID));

        $this->assertFalse($result['hasIsbn']);
        $this->assertSame([], $result['offers']);
        $this->assertNull($result['marketplace']);

        $this->assertCount(3, $result['retailers']);
        $this->assertSame(['amazon', 'fnac', 'ebay'], array_column($result['retailers'], 'retailer'));
        foreach ($result['retailers'] as $retailerBlock) {
            $this->assertSame('not_found', $retailerBlock['status']);
            $this->assertNull($retailerBlock['bestOffer']);
        }
    }

    public function testFreshOffersFeedTheRetailerBlocks(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(Isbn::fromString(self::ISBN)));
        $this->cache->method('get')->willReturn(null);
        $this->priceProvider->method('findOffers')->willReturn([
            $this->makeOffer('Amazon.fr', 7.20),
            $this->makeOffer('eBay', 5.40),
            $this->makeOffer('Rakuten', 6.50),
        ]);

        $result = ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID));

        $this->assertTrue($result['hasIsbn']);
        $this->assertSame('EBAY_FR', $result['marketplace']);
        $this->assertCount(3, $result['offers']);

        [$amazonBlock, $fnacBlock, $ebayBlock] = $result['retailers'];
        $this->assertSame('found', $amazonBlock['status']);
        $this->assertSame('Amazon.fr', $amazonBlock['bestOffer']['merchant']);
        $this->assertSame('not_found', $fnacBlock['status']);
        $this->assertNull($fnacBlock['bestOffer']);
        $this->assertSame('found', $ebayBlock['status']);
        $this->assertSame(5.40, $ebayBlock['bestOffer']['amount']);
    }

    public function testFreshOffersAreCached(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(Isbn::fromString(self::ISBN)));
        $this->cache->method('get')->willReturn(null);
        $this->priceProvider->method('findOffers')->willReturn([$this->makeOffer('Amazon', 7.20)]);

        $this->cache->expects($this->once())
            ->method('put')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(static fn (array $offers) => count($offers) === 1
                    && $offers[0]['merchant'] === 'Amazon'),
            );

        ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID));
    }

    public function testCachedOffersAlsoFeedTheRetailerBlocksWithoutProviderCall(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(Isbn::fromString(self::ISBN)));
        $this->cache->method('get')->willReturn([$this->makeOffer('Fnac', 7.90)->toArray()]);
        $this->priceProvider->expects($this->never())->method('findOffers');

        $result = ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID));

        [$amazonBlock, $fnacBlock, $ebayBlock] = $result['retailers'];
        $this->assertSame('not_found', $amazonBlock['status']);
        $this->assertSame('found', $fnacBlock['status']);
        $this->assertSame(7.90, $fnacBlock['bestOffer']['amount']);
        $this->assertSame('not_found', $ebayBlock['status']);
    }

    public function testMarketplaceQueryParamIsRespected(): void
    {
        $this->repository->method('findById')->willReturn($this->makeManga(Isbn::fromString(self::ISBN)));
        $this->cache->method('get')->willReturn(null);
        $this->priceProvider->method('findOffers')->willReturn([]);

        $result = ($this->handler)(new GetVolumePricesQuery(self::MANGA_ID, self::VOLUME_ID, 'EBAY_US'));

        $this->assertSame('EBAY_US', $result['marketplace']);
    }
}
