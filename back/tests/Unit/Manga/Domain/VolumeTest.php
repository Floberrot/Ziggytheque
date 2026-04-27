<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VolumeTest extends TestCase
{
    private function makeManga(): Manga
    {
        return new Manga(id: 'manga-1', title: 'Test', edition: null, language: 'fr');
    }

    public function testToArray(): void
    {
        $manga    = $this->makeManga();
        $released = new DateTimeImmutable('2023-06-15');
        $volume   = new Volume(
            id: 'vol-1',
            manga: $manga,
            number: 3,
            coverUrl: 'https://example.com/vol3.jpg',
            price: 7.50,
            releaseDate: $released,
        );

        $arr = $volume->toArray();

        $this->assertSame('vol-1', $arr['id']);
        $this->assertSame(3, $arr['number']);
        $this->assertSame('https://example.com/vol3.jpg', $arr['coverUrl']);
        $this->assertSame(7.50, $arr['price']);
        $this->assertStringContainsString('2023-06-15', $arr['releaseDate']);
    }

    public function testToArrayNullable(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v2', manga: $manga, number: 1);

        $arr = $volume->toArray();

        $this->assertNull($arr['coverUrl']);
        $this->assertNull($arr['price']);
        $this->assertNull($arr['releaseDate']);
    }
}
