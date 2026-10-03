<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\PurchaseVolume;

use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Collection\Application\PurchaseVolume\PurchaseVolumeHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\PurchaseVolumeFailedEvent;
use App\Collection\Shared\Event\PurchaseVolumeStartedEvent;
use App\Collection\Shared\Event\PurchaseVolumeSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class PurchaseVolumeHandlerTest extends TestCase
{
    public function testABoughtTomeIsOwnedAndNoLongerWished(): void
    {
        $entry = CollectedSeries::withTomes(2);
        CollectedSeries::tome($entry, 2)->isWished = true;

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        (new PurchaseVolumeHandler($repository, $eventBus))(new PurchaseVolumeCommand('entry-1', 'tome-2'));

        $this->assertTrue(CollectedSeries::tome($entry, 2)->isOwned);
        $this->assertFalse(CollectedSeries::tome($entry, 2)->isWished);
        $this->assertFalse(CollectedSeries::tome($entry, 1)->isOwned);
        $succeeded = $eventBus->first(PurchaseVolumeSucceededEvent::class);
        $this->assertSame('tome-2', $succeeded->volumeEntryId);
        $this->assertSame($eventBus->first(PurchaseVolumeStartedEvent::class)->correlationId, $succeeded->correlationId);
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new PurchaseVolumeHandler($repository, $eventBus))(new PurchaseVolumeCommand('ghost', 'tome-1'));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([PurchaseVolumeStartedEvent::class, PurchaseVolumeFailedEvent::class], $eventBus->eventClasses());
    }

    public function testAnUnknownTomeIsNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(CollectedSeries::withTomes(1));
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new PurchaseVolumeHandler($repository, $eventBus))(new PurchaseVolumeCommand('entry-1', 'tome-9'));
            $this->fail('An unknown tome must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame('tome-9', $eventBus->first(PurchaseVolumeFailedEvent::class)->volumeEntryId);
    }
}
