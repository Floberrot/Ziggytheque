<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Marketplace;
use PHPUnit\Framework\TestCase;

final class MarketplaceTest extends TestCase
{
    public function testEbayIdMatchesValue(): void
    {
        $this->assertSame('EBAY_FR', Marketplace::Fr->ebayId());
        $this->assertSame('EBAY_US', Marketplace::Us->ebayId());
    }

    public function testCurrencyCodeForFr(): void
    {
        $this->assertSame('EUR', Marketplace::Fr->currencyCode());
    }

    public function testCurrencyCodeForUs(): void
    {
        $this->assertSame('USD', Marketplace::Us->currencyCode());
    }

    public function testFromLanguageEnReturnsUs(): void
    {
        $this->assertSame(Marketplace::Us, Marketplace::fromLanguage('en'));
    }

    public function testFromLanguageFrReturnsFr(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromLanguage('fr'));
    }

    public function testFromLanguageNullReturnsFr(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromLanguage(null));
    }

    public function testFromLanguageUnknownReturnsFr(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromLanguage('ja'));
    }

    public function testFromValueWithValidString(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromValue('EBAY_FR'));
        $this->assertSame(Marketplace::Us, Marketplace::fromValue('EBAY_US'));
    }

    public function testFromValueWithNullReturnsFr(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromValue(null));
    }

    public function testFromValueWithUnknownStringReturnsFr(): void
    {
        $this->assertSame(Marketplace::Fr, Marketplace::fromValue('UNKNOWN'));
    }
}
