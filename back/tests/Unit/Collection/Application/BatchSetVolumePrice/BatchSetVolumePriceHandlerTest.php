<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\BatchSetVolumePrice;

use App\Collection\Application\BatchSetVolumePrice\BatchSetVolumePriceCommand;
use App\Collection\Application\BatchSetVolumePrice\BatchSetVolumePriceHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\BatchSetVolumePriceFailedEvent;
use App\Collection\Shared\Event\BatchSetVolumePriceStartedEvent;
use App\Collection\Shared\Event\BatchSetVolumePriceSucceededEvent;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class BatchSetVolumePriceHandlerTest extends TestCase
{
    public function testSetsThePriceOfEveryTomeOfTheSeries(): void
    {
        $entry      = CollectedSeries::withTomes(3);
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        (new BatchSetVolumePriceHandler($repository, $eventBus))(new BatchSetVolumePriceCommand('entry-1', 6.95));

        $this->assertSame(
            [6.95, 6.95, 6.95],
            array_map(static fn (Volume $volume): ?float => $volume->price, $entry->manga->volumes->toArray()),
        );
        $succeeded = $eventBus->first(BatchSetVolumePriceSucceededEvent::class);
        $this->assertSame(3, $succeeded->count);
        $this->assertSame(6.95, $succeeded->price);
        $this->assertSame($eventBus->first(BatchSetVolumePriceStartedEvent::class)->correlationId, $succeeded->correlationId);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new BatchSetVolumePriceHandler($repository, $eventBus))(new BatchSetVolumePriceCommand('ghost', 5.0));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame(
            [BatchSetVolumePriceStartedEvent::class, BatchSetVolumePriceFailedEvent::class],
            $eventBus->eventClasses(),
        );
    }
}
