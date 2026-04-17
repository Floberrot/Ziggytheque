<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Application\Import;

use App\Manga\Application\Import\ImportMangaCommand;
use App\Manga\Application\Import\ImportMangaHandler;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ImportMangaHandlerTest extends TestCase
{
    public function testCreatesAndSavesManga(): void
    {
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($this->isInstanceOf(Manga::class));

        $handler = new ImportMangaHandler($repository);
        $id = $handler(new ImportMangaCommand(
            title: 'Naruto',
            edition: 'Kana',
            language: 'fr',
            author: 'Kishimoto',
        ));

        $this->assertNotEmpty($id);
    }

    public function testCreatesVolumePlaceholdersWhenTotalVolumesProvided(): void
    {
        $savedManga = null;
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->once())->method('save')
            ->willReturnCallback(function (Manga $manga) use (&$savedManga) {
                $savedManga = $manga;
            });

        $handler = new ImportMangaHandler($repository);
        $handler(new ImportMangaCommand(
            title: 'One Piece',
            edition: 'Glenat',
            language: 'fr',
            totalVolumes: 3,
        ));

        $this->assertNotNull($savedManga);
        $this->assertCount(3, $savedManga->volumes);
    }

    public function testNoVolumesCreatedWhenTotalVolumesIsNull(): void
    {
        $savedManga = null;
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->expects($this->once())->method('save')
            ->willReturnCallback(function (Manga $manga) use (&$savedManga) {
                $savedManga = $manga;
            });

        $handler = new ImportMangaHandler($repository);
        $handler(new ImportMangaCommand('Berserk', 'Glenat', 'fr'));

        $this->assertCount(0, $savedManga->volumes);
    }

    public function testGenreIsSetFromString(): void
    {
        $savedManga = null;
        $repository = $this->createMock(MangaRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (Manga $m) use (&$savedManga) {
            $savedManga = $m;
        });

        $handler = new ImportMangaHandler($repository);
        $handler(new ImportMangaCommand('Demon Slayer', 'Kana', 'fr', genre: 'action'));

        $this->assertSame('action', $savedManga->genre?->value);
    }
}
