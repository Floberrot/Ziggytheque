<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\UpdateRating;

use App\Collection\Application\UpdateRating\UpdateRatingCommand;
use App\Collection\Application\UpdateRating\UpdateRatingHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\Exception\InvalidRatingException;
use App\Collection\Shared\Event\UpdateRatingFailedEvent;
use App\Collection\Shared\Event\UpdateRatingStartedEvent;
use App\Collection\Shared\Event\UpdateRatingSucceededEvent;
use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateRatingHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    private function entry(): CollectionEntry
    {
        return new CollectionEntry(id: 'entry-1', manga: new Manga(id: 'manga-1', title: 'Berserk', edition: null, language: 'fr'));
    }

    public function testStoresTheRatingAndJournalsIt(): void
    {
        $entry      = $this->entry();
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);

        (new UpdateRatingHandler($repository, $this->eventBus))(new UpdateRatingCommand('entry-1', 8));

        $this->assertSame(8, $entry->rating);
        $this->assertSame([UpdateRatingStartedEvent::class, UpdateRatingSucceededEvent::class], $this->eventBus->eventClasses());
        $started   = $this->eventBus->first(UpdateRatingStartedEvent::class);
        $succeeded = $this->eventBus->first(UpdateRatingSucceededEvent::class);
        $this->assertSame('entry-1', $started->collectionEntryId);
        $this->assertSame($started->correlationId, $succeeded->correlationId);
        $this->assertSame(8, $succeeded->rating);
    }

    /** @return iterable<string, array{int}> */
    public static function outOfRangeRatings(): iterable
    {
        yield 'below zero' => [-1];
        yield 'above ten'  => [11];
    }

    #[DataProvider('outOfRangeRatings')]
    public function testARatingOutOfRangeIsRefusedBeforeAnyRead(int $rating): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->never())->method('findById');
        $repository->expects($this->never())->method('save');

        try {
            (new UpdateRatingHandler($repository, $this->eventBus))(new UpdateRatingCommand('entry-1', $rating));
            $this->fail('An out-of-range rating must be refused.');
        } catch (InvalidRatingException) {
        }

        $this->assertSame([UpdateRatingStartedEvent::class, UpdateRatingFailedEvent::class], $this->eventBus->eventClasses());
        $failed = $this->eventBus->first(UpdateRatingFailedEvent::class);
        $this->assertSame(InvalidRatingException::class, $failed->exceptionClass);
        $this->assertSame($rating, $failed->rating);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $entry      = $this->entry();
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $handler = new UpdateRatingHandler($repository, $this->eventBus);

        $handler(new UpdateRatingCommand('entry-1', 0));
        $this->assertSame(0, $entry->rating);

        $handler(new UpdateRatingCommand('entry-1', 10));
        $this->assertSame(10, $entry->rating);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');

        try {
            (new UpdateRatingHandler($repository, $this->eventBus))(new UpdateRatingCommand('ghost', 5));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $failed = $this->eventBus->first(UpdateRatingFailedEvent::class);
        $this->assertSame('ghost', $failed->collectionEntryId);
        $this->assertSame(NotFoundException::class, $failed->exceptionClass);
    }
}
