<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain\Service;

use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use App\Manga\Domain\Service\PriceOfferSorter;
use PHPUnit\Framework\TestCase;

final class PriceOfferSorterTest extends TestCase
{
    private PriceOfferSorter $sorter;

    protected function setUp(): void
    {
        $this->sorter = new PriceOfferSorter();
    }

    private function makeOffer(PriceKindEnum $kind, float $amount): PriceOfferDto
    {
        return new PriceOfferDto(
            kind:         $kind,
            merchant:     'Test',
            merchantLogo: 'test',
            amount:       $amount,
            currency:     'EUR',
            url:          null,
            imageUrl:     null,
            source:       'test',
        );
    }

    public function testMerchantLiveComesBeforePublisherReference(): void
    {
        $reference = $this->makeOffer(PriceKindEnum::PublisherReference, 5.00);
        $live      = $this->makeOffer(PriceKindEnum::MerchantLive, 9.99);

        $sorted = $this->sorter->sort([$reference, $live]);

        $this->assertSame(PriceKindEnum::MerchantLive, $sorted[0]->kind);
        $this->assertSame(PriceKindEnum::PublisherReference, $sorted[1]->kind);
    }

    public function testWithinSameKindSortsByAmountAscending(): void
    {
        $expensive = $this->makeOffer(PriceKindEnum::MerchantLive, 15.00);
        $cheap     = $this->makeOffer(PriceKindEnum::MerchantLive, 7.50);
        $medium    = $this->makeOffer(PriceKindEnum::MerchantLive, 10.00);

        $sorted = $this->sorter->sort([$expensive, $cheap, $medium]);

        $this->assertSame(7.50, $sorted[0]->amount);
        $this->assertSame(10.00, $sorted[1]->amount);
        $this->assertSame(15.00, $sorted[2]->amount);
    }

    public function testMixedKindsAndAmounts(): void
    {
        $liveExpensive   = $this->makeOffer(PriceKindEnum::MerchantLive, 12.00);
        $liveCheap       = $this->makeOffer(PriceKindEnum::MerchantLive, 6.00);
        $refExpensive    = $this->makeOffer(PriceKindEnum::PublisherReference, 14.99);
        $refCheap        = $this->makeOffer(PriceKindEnum::PublisherReference, 8.99);

        $sorted = $this->sorter->sort([$liveExpensive, $refExpensive, $liveCheap, $refCheap]);

        $this->assertSame($liveCheap, $sorted[0]);
        $this->assertSame($liveExpensive, $sorted[1]);
        $this->assertSame($refCheap, $sorted[2]);
        $this->assertSame($refExpensive, $sorted[3]);
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->sorter->sort([]));
    }

    public function testDoesNotMutateOriginalOrder(): void
    {
        $a = $this->makeOffer(PriceKindEnum::PublisherReference, 5.00);
        $b = $this->makeOffer(PriceKindEnum::MerchantLive, 9.99);

        $original = [$a, $b];
        $this->sorter->sort($original);

        $this->assertSame($a, $original[0]);
        $this->assertSame($b, $original[1]);
    }
}
