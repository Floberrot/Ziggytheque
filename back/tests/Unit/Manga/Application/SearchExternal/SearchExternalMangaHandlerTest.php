<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\SearchExternal;

use App\Manga\Application\SearchExternal\SearchExternalMangaHandler;
use App\Manga\Application\SearchExternal\SearchExternalMangaQuery;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use PHPUnit\Framework\TestCase;

class SearchExternalMangaHandlerTest extends TestCase
{
    public function testReturnsMappedDtos(): void
    {
        $dto = new ExternalMangaDto(
            externalId: 'ext-1',
            title: 'One Piece',
            edition: 'Glenat',
            author: 'Oda',
            summary: 'Pirate adventure',
            coverUrl: 'https://cover.jpg',
            genre: 'action',
            language: 'fr',
            totalVolumes: 110,
        );

        $client = $this->createMock(ExternalApiClientInterface::class);
        $client->method('searchByTitle')->willReturn([$dto]);

        $handler = new SearchExternalMangaHandler($client);
        $result = $handler(new SearchExternalMangaQuery('one piece'));

        $this->assertCount(1, $result);
        $this->assertSame('ext-1', $result[0]['externalId']);
        $this->assertSame('One Piece', $result[0]['title']);
        $this->assertSame(110, $result[0]['totalVolumes']);
    }

    public function testReturnsEmptyArrayWhenNoResults(): void
    {
        $client = $this->createMock(ExternalApiClientInterface::class);
        $client->method('searchByTitle')->willReturn([]);

        $handler = new SearchExternalMangaHandler($client);
        $result = $handler(new SearchExternalMangaQuery('xyz'));

        $this->assertSame([], $result);
    }
}
