<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\Isbn;
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

    public function testConstructionKeepsEveryField(): void
    {
        $manga    = $this->makeManga();
        $released = new DateTimeImmutable('2023-06-15');
        $isbn     = Isbn::fromString('9782123456780');

        $volume = new Volume(
            id: 'vol-1',
            manga: $manga,
            number: 3,
            coverUrl: 'https://example.com/vol3.jpg',
            price: 7.50,
            releaseDate: $released,
            isbn: $isbn,
        );

        $this->assertSame('vol-1', $volume->id);
        $this->assertSame($manga, $volume->manga);
        $this->assertSame(3, $volume->number);
        $this->assertSame('https://example.com/vol3.jpg', $volume->coverUrl);
        $this->assertSame(7.50, $volume->price);
        $this->assertSame($released, $volume->releaseDate);
        $this->assertSame('9782123456780', $volume->isbn?->value);
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $volume = new Volume(id: 'v2', manga: $this->makeManga(), number: 1);

        $this->assertNull($volume->coverUrl);
        $this->assertNull($volume->price);
        $this->assertNull($volume->releaseDate);
        $this->assertNull($volume->isbn);
    }
}
