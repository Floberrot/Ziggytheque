<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\AddRemainingToWishlist;

use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistCommand;
use App\Collection\Application\AddRemainingToWishlist\AddRemainingToWishlistHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\AddRemainingToWishlistFailedEvent;
use App\Collection\Shared\Event\AddRemainingToWishlistStartedEvent;
use App\Collection\Shared\Event\AddRemainingToWishlistSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class AddRemainingToWishlistHandlerTest extends TestCase
{
    public function testWishesEveryTomeNeitherOwnedNorAlreadyWished(): void
    {
        $entry = CollectedSeries::withTomes(4);
        CollectedSeries::tome($entry, 1)->isOwned  = true;
        CollectedSeries::tome($entry, 2)->isWished = true;

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        (new AddRemainingToWishlistHandler($repository, $eventBus))(new AddRemainingToWishlistCommand('entry-1'));

        $this->assertFalse(CollectedSeries::tome($entry, 1)->isWished);
        $this->assertTrue(CollectedSeries::tome($entry, 2)->isWished);
        $this->assertTrue(CollectedSeries::tome($entry, 3)->isWished);
        $this->assertTrue(CollectedSeries::tome($entry, 4)->isWished);
        $this->assertSame(
            [AddRemainingToWishlistStartedEvent::class, AddRemainingToWishlistSucceededEvent::class],
            $eventBus->eventClasses(),
        );
        $this->assertSame(2, $eventBus->first(AddRemainingToWishlistSucceededEvent::class)->addedCount);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new AddRemainingToWishlistHandler($repository, $eventBus))(new AddRemainingToWishlistCommand('ghost'));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $failed = $eventBus->first(AddRemainingToWishlistFailedEvent::class);
        $this->assertSame('ghost', $failed->collectionEntryId);
        $this->assertSame($eventBus->first(AddRemainingToWishlistStartedEvent::class)->correlationId, $failed->correlationId);
    }
}
