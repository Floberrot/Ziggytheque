<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\PriceKindEnum;
use PHPUnit\Framework\TestCase;

final class PriceKindEnumTest extends TestCase
{
    public function testMerchantLiveValue(): void
    {
        $this->assertSame('merchant_live', PriceKindEnum::MerchantLive->value);
    }

    public function testPublisherReferenceValue(): void
    {
        $this->assertSame('publisher_reference', PriceKindEnum::PublisherReference->value);
    }

    public function testFromStringRoundtrip(): void
    {
        $this->assertSame(PriceKindEnum::MerchantLive, PriceKindEnum::from('merchant_live'));
        $this->assertSame(PriceKindEnum::PublisherReference, PriceKindEnum::from('publisher_reference'));
    }
}
