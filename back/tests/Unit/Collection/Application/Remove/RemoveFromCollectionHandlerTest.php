<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Remove;

use App\Collection\Application\Remove\RemoveFromCollectionCommand;
use App\Collection\Application\Remove\RemoveFromCollectionHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\RemoveFromCollectionFailedEvent;
use App\Collection\Shared\Event\RemoveFromCollectionStartedEvent;
use App\Collection\Shared\Event\RemoveFromCollectionSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class RemoveFromCollectionHandlerTest extends TestCase
{
    public function testDeletesTheEntryAndJournalsIt(): void
    {
        $entry      = CollectedSeries::withTomes(1);
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('delete')->with($entry);
        $eventBus = new RecordingEventBus();

        (new RemoveFromCollectionHandler($repository, $eventBus))(new RemoveFromCollectionCommand('entry-1'));

        $this->assertSame(
            [RemoveFromCollectionStartedEvent::class, RemoveFromCollectionSucceededEvent::class],
            $eventBus->eventClasses(),
        );
        $this->assertSame('entry-1', $eventBus->first(RemoveFromCollectionSucceededEvent::class)->collectionEntryId);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('delete');
        $eventBus = new RecordingEventBus();

        try {
            (new RemoveFromCollectionHandler($repository, $eventBus))(new RemoveFromCollectionCommand('ghost'));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame(
            [RemoveFromCollectionStartedEvent::class, RemoveFromCollectionFailedEvent::class],
            $eventBus->eventClasses(),
        );
    }
}
