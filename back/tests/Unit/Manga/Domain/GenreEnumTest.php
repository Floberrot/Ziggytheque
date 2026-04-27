<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\GenreEnum;
use PHPUnit\Framework\TestCase;

final class GenreEnumTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('shonen', GenreEnum::Shonen->value);
        $this->assertSame('shojo', GenreEnum::Shojo->value);
        $this->assertSame('seinen', GenreEnum::Seinen->value);
        $this->assertSame('josei', GenreEnum::Josei->value);
        $this->assertSame('kodomomuke', GenreEnum::Kodomomuke->value);
        $this->assertSame('isekai', GenreEnum::Isekai->value);
        $this->assertSame('fantasy', GenreEnum::Fantasy->value);
        $this->assertSame('action', GenreEnum::Action->value);
        $this->assertSame('romance', GenreEnum::Romance->value);
        $this->assertSame('horror', GenreEnum::Horror->value);
        $this->assertSame('sci_fi', GenreEnum::SciFi->value);
        $this->assertSame('slice_of_life', GenreEnum::SliceOfLife->value);
        $this->assertSame('sports', GenreEnum::Sports->value);
        $this->assertSame('other', GenreEnum::Other->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(GenreEnum::Shonen, GenreEnum::from('shonen'));
        $this->assertSame(GenreEnum::SciFi, GenreEnum::from('sci_fi'));
    }
}
