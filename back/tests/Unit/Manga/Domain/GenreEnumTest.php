<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\GenreEnum;
use PHPUnit\Framework\TestCase;

class GenreEnumTest extends TestCase
{
    public function testAllCasesHaveStringValues(): void
    {
        foreach (GenreEnum::cases() as $case) {
            $this->assertIsString($case->value);
            $this->assertNotEmpty($case->value);
        }
    }

    public function testFromStringReturnsCorrectCase(): void
    {
        $this->assertSame(GenreEnum::Action, GenreEnum::from('action'));
        $this->assertSame(GenreEnum::Shonen, GenreEnum::from('shonen'));
        $this->assertSame(GenreEnum::Romance, GenreEnum::from('romance'));
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        $this->assertNull(GenreEnum::tryFrom('unknown_genre'));
    }
}
