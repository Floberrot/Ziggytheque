<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\Volume;
use PHPUnit\Framework\TestCase;

class MangaTest extends TestCase
{
    public function testConstructorSetsRequiredFields(): void
    {
        $manga = new Manga('id-1', 'Naruto', 'Kana', 'fr');

        $this->assertSame('id-1', $manga->id);
        $this->assertSame('Naruto', $manga->title);
        $this->assertSame('Kana', $manga->edition);
        $this->assertSame('fr', $manga->language);
        $this->assertNull($manga->author);
        $this->assertNull($manga->genre);
        $this->assertCount(0, $manga->volumes);
        $this->assertInstanceOf(\DateTimeImmutable::class, $manga->createdAt);
    }

    public function testConstructorSetsOptionalFields(): void
    {
        $manga = new Manga(
            id: 'id-2',
            title: 'One Piece',
            edition: 'Glenat',
            language: 'fr',
            author: 'Oda',
            summary: 'A pirate story',
            coverUrl: 'https://example.com/cover.jpg',
            genre: GenreEnum::Action,
            externalId: 'ext-123',
        );

        $this->assertSame('Oda', $manga->author);
        $this->assertSame('A pirate story', $manga->summary);
        $this->assertSame(GenreEnum::Action, $manga->genre);
        $this->assertSame('ext-123', $manga->externalId);
    }

    public function testAddVolumeAddsOnce(): void
    {
        $manga = new Manga('id-1', 'Test', 'Ed', 'fr');
        $volume = new Volume('v-1', $manga, 1);

        $manga->addVolume($volume);
        $manga->addVolume($volume);

        $this->assertCount(1, $manga->volumes);
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $manga = new Manga('id-1', 'Naruto', 'Kana', 'fr');
        $arr = $manga->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('title', $arr);
        $this->assertArrayHasKey('edition', $arr);
        $this->assertArrayHasKey('language', $arr);
        $this->assertArrayHasKey('author', $arr);
        $this->assertArrayHasKey('totalVolumes', $arr);
        $this->assertArrayHasKey('createdAt', $arr);
        $this->assertSame(0, $arr['totalVolumes']);
    }

    public function testToDetailArrayIncludesVolumes(): void
    {
        $manga = new Manga('id-1', 'Naruto', 'Kana', 'fr');
        $manga->addVolume(new Volume('v-1', $manga, 1));

        $arr = $manga->toDetailArray();

        $this->assertArrayHasKey('volumes', $arr);
        $this->assertCount(1, $arr['volumes']);
    }
}
