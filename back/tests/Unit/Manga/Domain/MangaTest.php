<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

final class MangaTest extends TestCase
{
    private function makeManga(string $id = 'manga-1'): Manga
    {
        return new Manga(
            id: $id,
            title: 'One Piece',
            edition: 'Standard',
            language: 'fr',
            author: 'Oda Eiichiro',
            summary: 'A pirate adventure.',
            coverUrl: 'https://example.com/cover.jpg',
            genre: GenreEnum::Shonen,
            externalId: 'ext-123',
        );
    }

    public function testToArray(): void
    {
        $manga = $this->makeManga();
        $arr   = $manga->toArray();

        $this->assertSame('manga-1', $arr['id']);
        $this->assertSame('One Piece', $arr['title']);
        $this->assertSame('Standard', $arr['edition']);
        $this->assertSame('fr', $arr['language']);
        $this->assertSame('Oda Eiichiro', $arr['author']);
        $this->assertSame('shonen', $arr['genre']);
        $this->assertSame('ext-123', $arr['externalId']);
        $this->assertSame(0, $arr['totalVolumes']);
        $this->assertArrayHasKey('createdAt', $arr);
    }

    public function testToArrayNullableFields(): void
    {
        $manga = new Manga(id: 'm2', title: 'Test', edition: null, language: 'fr');
        $arr   = $manga->toArray();

        $this->assertNull($arr['edition']);
        $this->assertNull($arr['author']);
        $this->assertNull($arr['coverUrl']);
        $this->assertNull($arr['genre']);
        $this->assertNull($arr['externalId']);
    }

    public function testAddVolume(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v1', manga: $manga, number: 1, coverUrl: null, price: 7.99);
        $manga->addVolume($volume);

        $this->assertSame(1, $manga->volumes->count());
        $this->assertSame(1, $manga->toArray()['totalVolumes']);
    }

    public function testAddVolumeDuplicate(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v1', manga: $manga, number: 1);
        $manga->addVolume($volume);
        $manga->addVolume($volume);

        $this->assertSame(1, $manga->volumes->count());
    }

    public function testToDetailArray(): void
    {
        $manga  = $this->makeManga();
        $volume = new Volume(id: 'v1', manga: $manga, number: 1, price: 7.99);
        $manga->addVolume($volume);

        $detail = $manga->toDetailArray();

        $this->assertArrayHasKey('volumes', $detail);
        $this->assertCount(1, $detail['volumes']);
        $this->assertSame(1, $detail['volumes'][0]['number']);
    }
}
