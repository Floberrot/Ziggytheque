<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Enrich;

use App\Manga\Application\Enrich\EnrichMangaHandler;
use App\Manga\Application\Enrich\EnrichMangaMessage;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\MangaEnricher;
use PHPUnit\Framework\TestCase;

final class EnrichMangaHandlerTest extends TestCase
{
    private function match(): ExternalMangaDto
    {
        return new ExternalMangaDto('2', 'Berserk', null, 'Miura, Kentarou', 'Guts…', null, 'seinen', 'fr', 'jikan');
    }

    public function testFillsAndSavesTheSeries(): void
    {
        $manga = new Manga(id: 'm1', title: 'Berserk', edition: 'Glénat', language: 'fr');
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn($manga);
        $repository->expects($this->once())->method('save')->with($manga);
        $externalApi = $this->createStub(ExternalApiClientInterface::class);
        $externalApi->method('searchByTitle')->willReturn([$this->match()]);

        (new EnrichMangaHandler($repository, $externalApi, new MangaEnricher()))(new EnrichMangaMessage('m1'));

        $this->assertSame(GenreEnum::Seinen, $manga->genre);
    }

    public function testDoesNothingForAnUnknownOrCompleteSeries(): void
    {
        $complete = new Manga(id: 'm2', title: 'Berserk', edition: 'Glénat', language: 'fr', author: 'K. Miura', summary: 'S', genre: GenreEnum::Seinen);
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturnOnConsecutiveCalls(null, $complete);
        $repository->expects($this->never())->method('save');
        $externalApi = $this->createMock(ExternalApiClientInterface::class);
        $externalApi->expects($this->never())->method('searchByTitle');

        $handler = new EnrichMangaHandler($repository, $externalApi, new MangaEnricher());
        $handler(new EnrichMangaMessage('missing'));
        $handler(new EnrichMangaMessage('m2'));
    }

    public function testDoesNotSaveWhenNothingMatched(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn(new Manga(id: 'm3', title: 'Unknown', edition: null, language: 'fr'));
        $repository->expects($this->never())->method('save');
        $externalApi = $this->createStub(ExternalApiClientInterface::class);
        $externalApi->method('searchByTitle')->willReturn([$this->match()]);

        (new EnrichMangaHandler($repository, $externalApi, new MangaEnricher()))(new EnrichMangaMessage('m3'));
    }
}
