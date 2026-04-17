<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use App\PriceCode\Domain\PriceCode;
use PHPUnit\Framework\TestCase;

class VolumeTest extends TestCase
{
    private Manga $manga;

    protected function setUp(): void
    {
        $this->manga = new Manga('m-1', 'Test Manga', 'Ed', 'fr');
    }

    public function testConstructorSetsFields(): void
    {
        $volume = new Volume('v-1', $this->manga, 3);

        $this->assertSame('v-1', $volume->id);
        $this->assertSame($this->manga, $volume->manga);
        $this->assertSame(3, $volume->number);
        $this->assertNull($volume->coverUrl);
        $this->assertNull($volume->priceCode);
        $this->assertNull($volume->releaseDate);
    }

    public function testToArrayWithoutPriceCode(): void
    {
        $volume = new Volume('v-1', $this->manga, 2);
        $arr = $volume->toArray();

        $this->assertSame('v-1', $arr['id']);
        $this->assertSame(2, $arr['number']);
        $this->assertNull($arr['coverUrl']);
        $this->assertNull($arr['priceCode']);
        $this->assertNull($arr['releaseDate']);
    }

    public function testToArrayWithPriceCode(): void
    {
        $pc = new PriceCode('POCHE', 'Poche', 6.99);
        $volume = new Volume('v-1', $this->manga, 1, 'https://cover.jpg', $pc, new \DateTimeImmutable('2023-01-15'));
        $arr = $volume->toArray();

        $this->assertIsArray($arr['priceCode']);
        $this->assertSame('POCHE', $arr['priceCode']['code']);
        $this->assertNotNull($arr['releaseDate']);
    }
}
