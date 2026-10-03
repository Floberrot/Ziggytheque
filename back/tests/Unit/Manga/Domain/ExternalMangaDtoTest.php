<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\ExternalMangaDto;
use PHPUnit\Framework\TestCase;

final class ExternalMangaDtoTest extends TestCase
{
    public function testConstruction(): void
    {
        $dto = new ExternalMangaDto(
            externalId: 'ext-1',
            title: 'One Piece',
            edition: null,
            author: 'Oda',
            summary: 'A pirate adventure',
            coverUrl: 'https://example.com/cover.jpg',
            genre: 'shonen',
            language: 'fr',
            source: 'jikan',
            totalVolumes: 108,
        );

        $this->assertSame('ext-1', $dto->externalId);
        $this->assertSame('One Piece', $dto->title);
        $this->assertNull($dto->edition);
        $this->assertSame('Oda', $dto->author);
        $this->assertSame('fr', $dto->language);
        $this->assertSame(108, $dto->totalVolumes);
    }
}
