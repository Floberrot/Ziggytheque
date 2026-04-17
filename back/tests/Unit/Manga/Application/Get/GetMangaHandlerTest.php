<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Get;

use App\Manga\Application\Get\GetMangaHandler;
use App\Manga\Application\Get\GetMangaQuery;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class GetMangaHandlerTest extends TestCase
{
    public function testReturnsDetailArrayWhenFound(): void
    {
        $manga = new Manga('m-1', 'Naruto', 'Kana', 'fr');
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->with('m-1')->willReturn($manga);

        $handler = new GetMangaHandler($repository);
        $result = $handler(new GetMangaQuery('m-1'));

        $this->assertSame('m-1', $result['id']);
        $this->assertArrayHasKey('volumes', $result);
    }

    public function testThrowsNotFoundWhenMangaMissing(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new GetMangaHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new GetMangaQuery('missing'));
    }
}
