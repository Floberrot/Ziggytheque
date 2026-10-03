<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ToggleVolume;

use App\Collection\Application\ToggleVolume\ToggleVolumeCommand;
use App\Collection\Application\ToggleVolume\ToggleVolumeHandler;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeToggleFieldEnum;
use App\Collection\Shared\Event\ToggleVolumeFailedEvent;
use App\Collection\Shared\Event\ToggleVolumeStartedEvent;
use App\Collection\Shared\Event\ToggleVolumeSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ToggleVolumeHandlerTest extends TestCase
{
    private RecordingEventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new RecordingEventBus();
    }

    private function toggle(CollectionEntry $entry, string $volumeEntryId, VolumeToggleFieldEnum $field): void
    {
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);

        (new ToggleVolumeHandler($repository, $this->eventBus))(new ToggleVolumeCommand('entry-1', $volumeEntryId, $field));
    }

    public function testOwningATomeTakesItOffTheWishlistAndTheAnnouncements(): void
    {
        $entry = CollectedSeries::withTomes(2);
        $tome  = CollectedSeries::tome($entry, 1);
        $tome->isWished    = true;
        $tome->isAnnounced = true;

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        (new ToggleVolumeHandler($repository, $this->eventBus))(
            new ToggleVolumeCommand('entry-1', 'tome-1', VolumeToggleFieldEnum::IsOwned),
        );

        $this->assertTrue($tome->isOwned);
        $this->assertFalse($tome->isWished);
        $this->assertFalse($tome->isAnnounced);
        // One tome owned out of two: the reading status follows.
        $this->assertSame(ReadingStatusEnum::InProgress, $entry->readingStatus);
        $succeeded = $this->eventBus->first(ToggleVolumeSucceededEvent::class);
        $this->assertSame('isOwned', $succeeded->field);
        $this->assertSame('tome-1', $succeeded->volumeEntryId);
    }

    public function testTogglingOwnedAgainOnlyUnowns(): void
    {
        $entry = CollectedSeries::withTomes(1);
        $tome  = CollectedSeries::tome($entry, 1);
        $tome->isOwned = true;

        $this->toggle($entry, 'tome-1', VolumeToggleFieldEnum::IsOwned);

        $this->assertFalse($tome->isOwned);
        $this->assertFalse($tome->isWished);
    }

    /** @return iterable<string, array{VolumeToggleFieldEnum, string}> */
    public static function plainFlags(): iterable
    {
        yield 'read'      => [VolumeToggleFieldEnum::IsRead, 'isRead'];
        yield 'wished'    => [VolumeToggleFieldEnum::IsWished, 'isWished'];
        yield 'announced' => [VolumeToggleFieldEnum::IsAnnounced, 'isAnnounced'];
    }

    #[DataProvider('plainFlags')]
    public function testEveryOtherFlagFlipsBackAndForth(VolumeToggleFieldEnum $field, string $property): void
    {
        $entry = CollectedSeries::withTomes(1);
        $tome  = CollectedSeries::tome($entry, 1);

        $this->toggle($entry, 'tome-1', $field);
        $this->assertTrue($tome->{$property});

        $this->toggle($entry, 'tome-1', $field);
        $this->assertFalse($tome->{$property});
        $this->assertFalse($tome->isOwned);
    }

    public function testReadingEveryTomeCompletesTheSeries(): void
    {
        $entry = CollectedSeries::withTomes(1);

        $this->toggle($entry, 'tome-1', VolumeToggleFieldEnum::IsRead);

        $this->assertSame(ReadingStatusEnum::Completed, $entry->readingStatus);
    }

    public function testAnUnknownTomeIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(CollectedSeries::withTomes(1));
        $repository->expects($this->never())->method('save');

        try {
            (new ToggleVolumeHandler($repository, $this->eventBus))(
                new ToggleVolumeCommand('entry-1', 'tome-9', VolumeToggleFieldEnum::IsRead),
            );
            $this->fail('An unknown tome must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([ToggleVolumeStartedEvent::class, ToggleVolumeFailedEvent::class], $this->eventBus->eventClasses());
        $this->assertSame('isRead', $this->eventBus->first(ToggleVolumeFailedEvent::class)->field);
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        (new ToggleVolumeHandler($repository, $this->eventBus))(
            new ToggleVolumeCommand('ghost', 'tome-1', VolumeToggleFieldEnum::IsOwned),
        );
    }
}
