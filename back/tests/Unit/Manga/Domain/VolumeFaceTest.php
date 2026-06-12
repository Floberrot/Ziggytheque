<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\VolumeFace;
use PHPUnit\Framework\TestCase;

final class VolumeFaceTest extends TestCase
{
    public function testBackingValues(): void
    {
        $this->assertSame('cover', VolumeFace::Cover->value);
        $this->assertSame('spine', VolumeFace::Spine->value);
        $this->assertSame('back', VolumeFace::Back->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(VolumeFace::Back, VolumeFace::from('back'));
    }

    public function testTryFromRejectsUnknownFace(): void
    {
        $this->assertNull(VolumeFace::tryFrom('middle'));
    }
}
