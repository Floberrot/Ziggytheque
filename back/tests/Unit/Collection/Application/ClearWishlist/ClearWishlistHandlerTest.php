<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ClearWishlist;

use App\Collection\Application\ClearWishlist\ClearWishlistCommand;
use App\Collection\Application\ClearWishlist\ClearWishlistHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\ClearWishlistFailedEvent;
use App\Collection\Shared\Event\ClearWishlistStartedEvent;
use App\Collection\Shared\Event\ClearWishlistSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class ClearWishlistHandlerTest extends TestCase
{
    public function testUnwishesEveryTomeAndKeepsWhatIsOwned(): void
    {
        $entry = CollectedSeries::withTomes(3);
        CollectedSeries::tome($entry, 1)->isOwned  = true;
        CollectedSeries::tome($entry, 2)->isWished = true;
        CollectedSeries::tome($entry, 3)->isWished = true;

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        (new ClearWishlistHandler($repository, $eventBus))(new ClearWishlistCommand('entry-1'));

        $this->assertSame([false, false, false], array_map(
            static fn (int $number): bool => CollectedSeries::tome($entry, $number)->isWished,
            [1, 2, 3],
        ));
        $this->assertTrue(CollectedSeries::tome($entry, 1)->isOwned);
        $this->assertSame([ClearWishlistStartedEvent::class, ClearWishlistSucceededEvent::class], $eventBus->eventClasses());
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new ClearWishlistHandler($repository, $eventBus))(new ClearWishlistCommand('ghost'));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([ClearWishlistStartedEvent::class, ClearWishlistFailedEvent::class], $eventBus->eventClasses());
        $this->assertSame('ghost', $eventBus->first(ClearWishlistFailedEvent::class)->collectionEntryId);
    }
}
