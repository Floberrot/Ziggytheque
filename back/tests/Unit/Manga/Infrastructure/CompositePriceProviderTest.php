<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\VolumePriceProviderInterface;
use App\Manga\Infrastructure\ExternalApi\CompositePriceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

final class CompositePriceProviderTest extends TestCase
{
    private Isbn $isbn;

    protected function setUp(): void
    {
        $this->isbn = Isbn::fromString('9782723425483');
    }

    private function makeOffer(string $source): PriceOfferDto
    {
        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     'Test',
            merchantLogo: 'test',
            amount:       9.99,
            currency:     'EUR',
            url:          null,
            imageUrl:     null,
            source:       $source,
        );
    }

    /** @param list<PriceOfferDto> $offers */
    private function makeProvider(array $offers): VolumePriceProviderInterface
    {
        return new class ($offers) implements VolumePriceProviderInterface {
            /** @param list<PriceOfferDto> $offers */
            public function __construct(private readonly array $offers)
            {
            }

            public function findOffers(Isbn $isbn, Marketplace $marketplace): array
            {
                return $this->offers;
            }
        };
    }

    private function throwingProvider(): VolumePriceProviderInterface
    {
        return new class implements VolumePriceProviderInterface {
            public function findOffers(Isbn $isbn, Marketplace $marketplace): array
            {
                throw new RuntimeException('upstream down');
            }
        };
    }

    public function testMergesOffersFromAllProviders(): void
    {
        $offerA = $this->makeOffer('ebay');
        $offerB = $this->makeOffer('google_books');

        $composite = new CompositePriceProvider(
            [$this->makeProvider([$offerA]), $this->makeProvider([$offerB])],
            new NullLogger(),
        );

        $result = $composite->findOffers($this->isbn, Marketplace::Fr);

        $this->assertSame([$offerA, $offerB], $result);
    }

    public function testSkipsFailingProviderWithoutException(): void
    {
        $offer = $this->makeOffer('ebay');

        $composite = new CompositePriceProvider(
            [$this->throwingProvider(), $this->makeProvider([$offer])],
            new NullLogger(),
        );

        $result = $composite->findOffers($this->isbn, Marketplace::Fr);

        $this->assertSame([$offer], $result);
    }

    public function testDoesNotSortOffers(): void
    {
        $offerFirst  = $this->makeOffer('ebay');
        $offerSecond = $this->makeOffer('google_books');

        $composite = new CompositePriceProvider(
            [$this->makeProvider([$offerFirst, $offerSecond])],
            new NullLogger(),
        );

        $result = $composite->findOffers($this->isbn, Marketplace::Fr);

        $this->assertSame($offerFirst, $result[0]);
        $this->assertSame($offerSecond, $result[1]);
    }

    public function testReturnsEmptyWhenNoProviders(): void
    {
        $composite = new CompositePriceProvider([], new NullLogger());

        $this->assertSame([], $composite->findOffers($this->isbn, Marketplace::Fr));
    }
}
