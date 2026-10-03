<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\SyncVolumes;

use App\Collection\Application\SyncVolumes\SyncVolumesCommand;
use App\Collection\Application\SyncVolumes\SyncVolumesHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\SyncVolumesFailedEvent;
use App\Collection\Shared\Event\SyncVolumesStartedEvent;
use App\Collection\Shared\Event\SyncVolumesSucceededEvent;
use App\Manga\Domain\Exception\TooManyVolumesException;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class SyncVolumesHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    public function testGrowsTheSeriesUpToTheAskedTomeAndTracksTheNewOnes(): void
    {
        $entry = CollectedSeries::withTomes(2);

        $collectionRepository = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $collectionRepository->expects($this->once())->method('save')->with($entry);
        $mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepository->expects($this->once())->method('save')->with($entry->manga);

        (new SyncVolumesHandler($collectionRepository, $mangaRepository, $this->eventBus))(new SyncVolumesCommand('entry-1', 5));

        $this->assertSame(
            [1, 2, 3, 4, 5],
            array_map(static fn (Volume $volume): int => $volume->number, $entry->manga->volumes->toArray()),
        );
        $this->assertCount(5, $entry->volumeEntries);
        $this->assertSame(3, $this->eventBus->first(SyncVolumesSucceededEvent::class)->addedCount);
    }

    /** Without a target, only tomes the series already has but the entry misses are tracked. */
    public function testWithoutATargetOnlyTracksTheTomesTheSeriesAlreadyHas(): void
    {
        $entry = CollectedSeries::withTomes(1);
        $entry->manga->ensureVolumesUpTo(2);

        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findById')->willReturn($entry);
        $mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepository->expects($this->never())->method('save');

        (new SyncVolumesHandler($collectionRepository, $mangaRepository, $this->eventBus))(new SyncVolumesCommand('entry-1'));

        $this->assertCount(2, $entry->manga->volumes);
        $this->assertCount(2, $entry->volumeEntries);
        $this->assertSame(1, $this->eventBus->first(SyncVolumesSucceededEvent::class)->addedCount);
    }

    public function testAnAbsurdTargetIsRefusedAndJournaledAsFailed(): void
    {
        $collectionRepository = $this->createMock(CollectionRepositoryInterface::class);
        $collectionRepository->expects($this->once())->method('findById')->willReturn(CollectedSeries::withTomes(1));
        $collectionRepository->expects($this->never())->method('save');
        $mangaRepository = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepository->expects($this->never())->method('save');

        try {
            (new SyncVolumesHandler($collectionRepository, $mangaRepository, $this->eventBus))(new SyncVolumesCommand('entry-1', 100_000));
            $this->fail('An absurd tome count must be refused.');
        } catch (TooManyVolumesException) {
        }

        $this->assertSame([SyncVolumesStartedEvent::class, SyncVolumesFailedEvent::class], $this->eventBus->eventClasses());
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $collectionRepository = $this->createStub(CollectionRepositoryInterface::class);
        $collectionRepository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        (new SyncVolumesHandler($collectionRepository, $this->createStub(MangaRepositoryInterface::class), $this->eventBus))(
            new SyncVolumesCommand('ghost', 3),
        );
    }
}
