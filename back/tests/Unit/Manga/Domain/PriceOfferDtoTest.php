<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\PriceKindEnum;
use App\Manga\Domain\PriceOfferDto;
use PHPUnit\Framework\TestCase;

final class PriceOfferDtoTest extends TestCase
{
    private function makeDto(): PriceOfferDto
    {
        return new PriceOfferDto(
            kind:         PriceKindEnum::MerchantLive,
            merchant:     'eBay',
            merchantLogo: 'ebay',
            amount:       7.50,
            currency:     'EUR',
            url:          'https://ebay.fr/item/123',
            imageUrl:     'https://ebay.fr/img/123.jpg',
            source:       'ebay',
        );
    }

    public function testConstructionSetsAllFields(): void
    {
        $dto = $this->makeDto();

        $this->assertSame(PriceKindEnum::MerchantLive, $dto->kind);
        $this->assertSame('eBay', $dto->merchant);
        $this->assertSame('ebay', $dto->merchantLogo);
        $this->assertSame(7.50, $dto->amount);
        $this->assertSame('EUR', $dto->currency);
        $this->assertSame('https://ebay.fr/item/123', $dto->url);
        $this->assertSame('https://ebay.fr/img/123.jpg', $dto->imageUrl);
        $this->assertSame('ebay', $dto->source);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $array = $this->makeDto()->toArray();

        $this->assertArrayHasKey('kind', $array);
        $this->assertArrayHasKey('merchant', $array);
        $this->assertArrayHasKey('merchantLogo', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('imageUrl', $array);
        $this->assertArrayHasKey('source', $array);
    }

    public function testToArraySerializesKindAsValue(): void
    {
        $this->assertSame('merchant_live', $this->makeDto()->toArray()['kind']);
    }

    public function testToArrayWithNullableUrl(): void
    {
        $dto = new PriceOfferDto(
            kind:         PriceKindEnum::PublisherReference,
            merchant:     'Google Play',
            merchantLogo: 'google_play',
            amount:       9.99,
            currency:     'EUR',
            url:          null,
            imageUrl:     null,
            source:       'google_books',
        );

        $array = $dto->toArray();

        $this->assertSame('publisher_reference', $array['kind']);
        $this->assertNull($array['url']);
        $this->assertNull($array['imageUrl']);
    }
}
