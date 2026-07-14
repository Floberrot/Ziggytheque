<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\Service\RetailerOfferResolver;
use PHPUnit\Framework\TestCase;

final class RetailerOfferResolverTest extends TestCase
{
    private RetailerOfferResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RetailerOfferResolver();
    }

    /** @return array<string, mixed> */
    private function offer(string $merchant, float $amount, string $kind = 'merchant_live'): array
    {
        return [
            'kind'         => $kind,
            'merchant'     => $merchant,
            'merchantLogo' => 'test',
            'amount'       => $amount,
            'currency'     => 'EUR',
            'url'          => 'https://shop.example/offer',
            'imageUrl'     => null,
            'source'       => 'test',
        ];
    }

    public function testEmptyOffersYieldThreeNotFoundBlocks(): void
    {
        $retailers = $this->resolver->resolve([]);

        $this->assertCount(3, $retailers);
        $this->assertSame(['amazon', 'fnac', 'ebay'], array_column($retailers, 'retailer'));
        $this->assertSame(['Amazon', 'Fnac', 'eBay'], array_column($retailers, 'label'));

        foreach ($retailers as $retailerBlock) {
            $this->assertSame(RetailerOfferResolver::STATUS_NOT_FOUND, $retailerBlock['status']);
            $this->assertNull($retailerBlock['bestOffer']);
        }
    }

    public function testMapsEachTargetShopToItsOffer(): void
    {
        $amazonOffer = $this->offer('Amazon.fr', 7.20);
        $fnacOffer   = $this->offer('Fnac.com', 7.90);
        $ebayOffer   = $this->offer('eBay France', 5.40);

        $retailers = $this->resolver->resolve([$amazonOffer, $fnacOffer, $ebayOffer]);

        $this->assertSame(RetailerOfferResolver::STATUS_FOUND, $retailers[0]['status']);
        $this->assertSame($amazonOffer, $retailers[0]['bestOffer']);
        $this->assertSame(RetailerOfferResolver::STATUS_FOUND, $retailers[1]['status']);
        $this->assertSame($fnacOffer, $retailers[1]['bestOffer']);
        $this->assertSame(RetailerOfferResolver::STATUS_FOUND, $retailers[2]['status']);
        $this->assertSame($ebayOffer, $retailers[2]['bestOffer']);
    }

    public function testKeepsTheCheapestOfferPerRetailer(): void
    {
        $expensiveAmazon = $this->offer('Amazon', 9.99);
        $cheapAmazon     = $this->offer('Amazon Marketplace', 6.50);

        $retailers = $this->resolver->resolve([$expensiveAmazon, $cheapAmazon]);

        $this->assertSame($cheapAmazon, $retailers[0]['bestOffer']);
    }

    public function testUnmappedMerchantLeavesRetailerNotFound(): void
    {
        $retailers = $this->resolver->resolve([
            $this->offer('Rakuten', 6.50),
            $this->offer('momox', 4.99),
        ]);

        foreach ($retailers as $retailerBlock) {
            $this->assertSame(RetailerOfferResolver::STATUS_NOT_FOUND, $retailerBlock['status']);
            $this->assertNull($retailerBlock['bestOffer']);
        }
    }

    public function testPublisherReferenceOffersNeverFeedRetailers(): void
    {
        $retailers = $this->resolver->resolve([
            $this->offer('Amazon', 7.20, kind: 'publisher_reference'),
        ]);

        $this->assertSame(RetailerOfferResolver::STATUS_NOT_FOUND, $retailers[0]['status']);
        $this->assertNull($retailers[0]['bestOffer']);
    }

    public function testMixedOffersOnlyFillTheMatchingShops(): void
    {
        $amazonOffer = $this->offer('Amazon', 7.20);

        $retailers = $this->resolver->resolve([
            $amazonOffer,
            $this->offer('Rakuten', 6.50),
            $this->offer('Google Play', 4.99, kind: 'publisher_reference'),
        ]);

        $this->assertSame(RetailerOfferResolver::STATUS_FOUND, $retailers[0]['status']);
        $this->assertSame($amazonOffer, $retailers[0]['bestOffer']);
        $this->assertSame(RetailerOfferResolver::STATUS_NOT_FOUND, $retailers[1]['status']);
        $this->assertSame(RetailerOfferResolver::STATUS_NOT_FOUND, $retailers[2]['status']);
    }
}
