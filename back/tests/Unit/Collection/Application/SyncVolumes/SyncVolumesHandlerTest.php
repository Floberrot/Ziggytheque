<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\SyncVolumes;

use App\Collection\Application\SyncVolumes\SyncVolumesCommand;
use App\Collection\Application\SyncVolumes\SyncVolumesHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class SyncVolumesHandlerTest extends TestCase
{
    public function testCreatesMissingVolumeEntriesForExistingVolumes(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $v1 = new Volume('v-1', $manga, 1);
        $v2 = new Volume('v-2', $manga, 2);
        $manga->addVolume($v1);
        $manga->addVolume($v2);

        $entry = new CollectionEntry('ce-1', $manga);
        // Only v1 is tracked, v2 is missing
        $entry->volumeEntries->add(new VolumeEntry('ve-1', $entry, $v1));

        $collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepo->method('findById')->willReturn($entry);
        $collectionRepo->expects($this->once())->method('save');

        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);

        $handler = new SyncVolumesHandler($collectionRepo, $mangaRepo);
        $handler(new SyncVolumesCommand('ce-1'));

        $this->assertCount(2, $entry->volumeEntries);
    }

    public function testCreatesVolumePlaceholdersUpToUpToVolume(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $v1 = new Volume('v-1', $manga, 1);
        $manga->addVolume($v1);

        $entry = new CollectionEntry('ce-1', $manga);
        $entry->volumeEntries->add(new VolumeEntry('ve-1', $entry, $v1));

        $collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepo->method('findById')->willReturn($entry);
        $collectionRepo->expects($this->once())->method('save');

        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->expects($this->once())->method('save')->with($manga);

        $handler = new SyncVolumesHandler($collectionRepo, $mangaRepo);
        $handler(new SyncVolumesCommand('ce-1', 3));

        $this->assertCount(3, $manga->volumes);
        $this->assertCount(3, $entry->volumeEntries);
    }

    public function testThrowsWhenEntryNotFound(): void
    {
        $collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepo->method('findById')->willReturn(null);
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);

        $handler = new SyncVolumesHandler($collectionRepo, $mangaRepo);

        $this->expectException(NotFoundException::class);
        $handler(new SyncVolumesCommand('missing'));
    }
}
