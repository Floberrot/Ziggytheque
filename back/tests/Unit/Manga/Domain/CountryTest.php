<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Country;
use PHPUnit\Framework\TestCase;

final class CountryTest extends TestCase
{
    public function testLanguageForFrance(): void
    {
        $this->assertSame('fr', Country::France->language());
    }

    public function testLanguageForUnitedStates(): void
    {
        $this->assertSame('en', Country::UnitedStates->language());
    }

    public function testLanguageForItaly(): void
    {
        $this->assertSame('it', Country::Italy->language());
    }

    public function testLanguageForSpain(): void
    {
        $this->assertSame('es', Country::Spain->language());
    }

    public function testLanguageForGermany(): void
    {
        $this->assertSame('de', Country::Germany->language());
    }

    public function testLanguageForJapan(): void
    {
        $this->assertSame('ja', Country::Japan->language());
    }

    public function testDefaultIsFrance(): void
    {
        $this->assertSame(Country::France, Country::default());
    }

    public function testFromCodeResolvesKnownCountry(): void
    {
        $this->assertSame(Country::UnitedStates, Country::fromCode('US'));
    }

    public function testFromCodeResolvesItaly(): void
    {
        $this->assertSame(Country::Italy, Country::fromCode('IT'));
    }

    public function testFromCodeResolvesSpain(): void
    {
        $this->assertSame(Country::Spain, Country::fromCode('ES'));
    }

    public function testFromCodeResolvesGermany(): void
    {
        $this->assertSame(Country::Germany, Country::fromCode('DE'));
    }

    public function testFromCodeIsCaseInsensitive(): void
    {
        $this->assertSame(Country::Japan, Country::fromCode('jp'));
    }

    public function testFromCodeTrimsWhitespace(): void
    {
        $this->assertSame(Country::France, Country::fromCode('  FR  '));
    }

    public function testFromCodeFallsBackToDefaultOnUnknownCode(): void
    {
        $this->assertSame(Country::France, Country::fromCode('XX'));
    }

    public function testFromCodeFallsBackToDefaultOnNull(): void
    {
        $this->assertSame(Country::France, Country::fromCode(null));
    }

    public function testFromCodeFallsBackToDefaultOnEmptyString(): void
    {
        $this->assertSame(Country::France, Country::fromCode(''));
    }
}
