<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Update;

use App\Manga\Application\Update\UpdateMangaCommand;
use App\Manga\Application\Update\UpdateMangaHandler;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class UpdateMangaHandlerTest extends TestCase
{
    public function testUpdatesTitleAndEdition(): void
    {
        $manga = new Manga('m-1', 'Old Title', 'Old Edition', 'fr');
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn($manga);
        $repository->expects($this->once())->method('save')->with($manga);

        $handler = new UpdateMangaHandler($repository);
        $handler(new UpdateMangaCommand('m-1', 'New Title', 'New Edition'));

        $this->assertSame('New Title', $manga->title);
        $this->assertSame('New Edition', $manga->edition);
    }

    public function testOnlyUpdatesTitleWhenEditionIsNull(): void
    {
        $manga = new Manga('m-1', 'Old Title', 'Old Edition', 'fr');
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn($manga);

        $handler = new UpdateMangaHandler($repository);
        $handler(new UpdateMangaCommand('m-1', 'New Title', null));

        $this->assertSame('New Title', $manga->title);
        $this->assertSame('Old Edition', $manga->edition);
    }

    public function testThrowsWhenMangaNotFound(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new UpdateMangaHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new UpdateMangaCommand('missing', 'Title', null));
    }
}
