<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Add;

use App\Collection\Application\Add\AddToCollectionCommand;
use App\Collection\Application\Add\AddToCollectionHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class AddToCollectionHandlerTest extends TestCase
{
    public function testCreatesEntryWithVolumeEntries(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $manga->addVolume(new Volume('v-1', $manga, 1));
        $manga->addVolume(new Volume('v-2', $manga, 2));

        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn($manga);

        $saved = null;
        $collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepo->expects($this->once())->method('save')
            ->willReturnCallback(function (CollectionEntry $e) use (&$saved) {
                $saved = $e;
            });

        $handler = new AddToCollectionHandler($collectionRepo, $mangaRepo);
        $id = $handler(new AddToCollectionCommand('m-1'));

        $this->assertNotEmpty($id);
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->volumeEntries);
    }

    public function testThrowsWhenMangaNotFound(): void
    {
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn(null);
        $collectionRepo = $this->createMock(CollectionRepositoryInterface::class);

        $handler = new AddToCollectionHandler($collectionRepo, $mangaRepo);

        $this->expectException(NotFoundException::class);
        $handler(new AddToCollectionCommand('missing'));
    }
}
