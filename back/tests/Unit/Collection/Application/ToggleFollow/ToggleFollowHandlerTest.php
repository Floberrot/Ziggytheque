<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\ToggleFollow;

use App\Collection\Application\ToggleFollow\ToggleFollowCommand;
use App\Collection\Application\ToggleFollow\ToggleFollowHandler;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\ToggleFollowFailedEvent;
use App\Collection\Shared\Event\ToggleFollowStartedEvent;
use App\Collection\Shared\Event\ToggleFollowSucceededEvent;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Doubles\Shared\RecordingEventBus;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ToggleFollowHandlerTest extends TestCase
{
    public function testFollowingStampsWhenItStarted(): void
    {
        $entry      = CollectedSeries::withTomes(1);
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with('entry-1')->willReturn($entry);
        $repository->expects($this->once())->method('save')->with($entry);
        $eventBus = new RecordingEventBus();

        $enabled = (new ToggleFollowHandler($repository, $eventBus))(new ToggleFollowCommand('entry-1'));

        $this->assertTrue($enabled);
        $this->assertTrue($entry->notificationsEnabled);
        $this->assertNotNull($entry->notificationsEnabledAt);
        $this->assertTrue($eventBus->first(ToggleFollowSucceededEvent::class)->enabled);
    }

    /** Unfollowing leaves the date of the last follow as it was. */
    public function testUnfollowingKeepsTheStartDate(): void
    {
        $since = new DateTimeImmutable('-3 days');
        $entry = CollectedSeries::withTomes(1);
        $entry->notificationsEnabled   = true;
        $entry->notificationsEnabledAt = $since;
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);
        $eventBus = new RecordingEventBus();

        $enabled = (new ToggleFollowHandler($repository, $eventBus))(new ToggleFollowCommand('entry-1'));

        $this->assertFalse($enabled);
        $this->assertFalse($entry->notificationsEnabled);
        $this->assertSame($since, $entry->notificationsEnabledAt);
        $this->assertFalse($eventBus->first(ToggleFollowSucceededEvent::class)->enabled);
    }

    public function testAnUnknownEntryIsNotFoundAndJournaledAsFailed(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');
        $eventBus = new RecordingEventBus();

        try {
            (new ToggleFollowHandler($repository, $eventBus))(new ToggleFollowCommand('ghost'));
            $this->fail('An unknown entry must be refused.');
        } catch (NotFoundException) {
        }

        $this->assertSame([ToggleFollowStartedEvent::class, ToggleFollowFailedEvent::class], $eventBus->eventClasses());
    }
}
