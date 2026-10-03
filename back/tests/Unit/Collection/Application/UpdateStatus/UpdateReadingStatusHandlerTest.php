<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\UpdateStatus;

use App\Collection\Application\UpdateStatus\UpdateReadingStatusCommand;
use App\Collection\Application\UpdateStatus\UpdateReadingStatusHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Shared\Event\UpdateReadingStatusFailedEvent;
use App\Collection\Shared\Event\UpdateReadingStatusStartedEvent;
use App\Collection\Shared\Event\UpdateReadingStatusSucceededEvent;
use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\TestCase;

final class UpdateReadingStatusHandlerTest extends TestCase
{
    public function testStoresTheStatusAndJournalsIt(): void
    {
        $entry      = new CollectionEntry(id: 'entry-1', manga: new Manga(id: 'manga-1', title: 'Berserk', edition: null, language: 'fr'));
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        (new UpdateReadingStatusHandler($repository, $eventBus))(
            new UpdateReadingStatusCommand('entry-1', ReadingStatusEnum::OnHold),
        );

        $this->assertSame(ReadingStatusEnum::OnHold, $entry->readingStatus);
        $this->assertSame(
            [UpdateReadingStatusStartedEvent::class, UpdateReadingStatusSucceededEvent::class],
            $eventBus->eventClasses(),
        );
        $started   = $eventBus->first(UpdateReadingStatusStartedEvent::class);
        $succeeded = $eventBus->first(UpdateReadingStatusSucceededEvent::class);
        $this->assertSame('entry-1', $started->collectionEntryId);
        $this->assertSame('on_hold', $started->status);
        $this->assertSame($started->correlationId, $succeeded->correlationId);
        $this->assertSame('on_hold', $succeeded->status);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new UpdateReadingStatusHandler($repository, $eventBus))(
                new UpdateReadingStatusCommand('ghost', ReadingStatusEnum::Completed),
            );
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame(
            [UpdateReadingStatusStartedEvent::class, UpdateReadingStatusFailedEvent::class],
            $eventBus->eventClasses(),
        );
        $failed = $eventBus->first(UpdateReadingStatusFailedEvent::class);
        $this->assertSame('ghost', $failed->collectionEntryId);
        $this->assertSame('completed', $failed->status);
        $this->assertSame(NotFoundException::class, $failed->exceptionClass);
        $this->assertSame($eventBus->first(UpdateReadingStatusStartedEvent::class)->correlationId, $failed->correlationId);
    }
}
