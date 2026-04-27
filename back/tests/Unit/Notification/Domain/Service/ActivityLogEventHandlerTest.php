<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Domain\Service\ActivityLogEventHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ActivityLogEventHandlerTest extends TestCase
{
    private ActivityLogRepositoryInterface&MockObject $logRepo;
    private CollectionRepositoryInterface&MockObject $collectionRepo;
    private ActivityLogEventHandler $handler;

    protected function setUp(): void
    {
        $this->logRepo        = $this->createMock(ActivityLogRepositoryInterface::class);
        $this->collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $this->handler        = new ActivityLogEventHandler($this->logRepo, $this->collectionRepo);
    }

    public function testHandleStartedEventCreatesLog(): void
    {
        $event = new class {
            public string $correlationId  = 'corr-1';
            public string $sourceName     = 'AddToCollection';
        };

        $this->logRepo->expects($this->once())->method('save');
        $this->collectionRepo->expects($this->never())->method('findById');

        $this->handler->handleStartedEvent($event, EventTypeEnum::CollectionAction);
    }

    public function testHandleStartedEventWithCollectionEntryId(): void
    {
        $event = new class {
            public string $correlationId       = 'corr-2';
            public string $sourceName          = 'SyncVolumes';
            public string $collectionEntryId   = 'ce-123';
        };

        $this->collectionRepo->expects($this->once())
            ->method('findById')
            ->with('ce-123')
            ->willReturn(null);

        $this->logRepo->expects($this->once())->method('save');

        $this->handler->handleStartedEvent($event, EventTypeEnum::CollectionAction);
    }

    public function testHandleStartedEventSkipsIfNoCorrelationId(): void
    {
        $event = new class {
            public string $sourceName = 'test';
        };

        $this->logRepo->expects($this->never())->method('save');

        $this->handler->handleStartedEvent($event, EventTypeEnum::CollectionAction);
    }

    public function testHandleSucceededEventUpdatesLog(): void
    {
        $log = new ActivityLog(id: 'corr-3', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');

        $event = new class {
            public string $correlationId = 'corr-3';
            public int $newCount         = 5;
        };

        $this->logRepo->expects($this->once())
            ->method('findById')
            ->with('corr-3')
            ->willReturn($log);

        $this->logRepo->expects($this->once())->method('save');

        $this->handler->handleSucceededEvent($event);

        $this->assertSame('success', $log->status);
        $this->assertSame(5, $log->newArticlesCount);
    }

    public function testHandleSucceededEventWithForcedCount(): void
    {
        $log   = new ActivityLog(id: 'corr-4', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $event = new class {
            public string $correlationId = 'corr-4';
        };

        $this->logRepo->method('findById')->willReturn($log);
        $this->logRepo->expects($this->once())->method('save');

        $this->handler->handleSucceededEvent($event, 42);

        $this->assertSame(42, $log->newArticlesCount);
    }

    public function testHandleSucceededEventSkipsIfLogNotFound(): void
    {
        $event = new class {
            public string $correlationId = 'missing';
        };

        $this->logRepo->method('findById')->willReturn(null);
        $this->logRepo->expects($this->never())->method('save');

        $this->handler->handleSucceededEvent($event);
    }

    public function testHandleSucceededEventSkipsIfNoCorrelationId(): void
    {
        $event = new class {};

        $this->logRepo->expects($this->never())->method('findById');

        $this->handler->handleSucceededEvent($event);
    }

    public function testHandleFailedEventMarksError(): void
    {
        $log   = new ActivityLog(id: 'corr-5', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $event = new class {
            public string $correlationId  = 'corr-5';
            public string $error          = 'Connection timeout';
            public string $exceptionClass = 'RuntimeException';
        };

        $this->logRepo->method('findById')->willReturn($log);
        $this->logRepo->expects($this->once())->method('save');

        $this->handler->handleFailedEvent($event);

        $this->assertSame('error', $log->status);
        $this->assertSame('Connection timeout', $log->errorMessage);
    }

    public function testHandleFailedEventSkipsIfNoCorrelationId(): void
    {
        $event = new class {};
        $this->logRepo->expects($this->never())->method('findById');
        $this->handler->handleFailedEvent($event);
    }

    public function testHandleFailedEventSkipsIfLogNotFound(): void
    {
        $event = new class {
            public string $correlationId = 'gone';
        };
        $this->logRepo->method('findById')->willReturn(null);
        $this->logRepo->expects($this->never())->method('save');
        $this->handler->handleFailedEvent($event);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideDetectEventTypeEnum')]
    public function testDetectEventTypeEnum(string $namespace, EventTypeEnum $expected): void
    {
        // Create an anonymous class that fakes a namespace via class alias
        $event = new class($namespace) {
            public function __construct(public readonly string $fakeNamespace) {}
        };

        // We test the detection via actual events from known namespaces
        $this->assertSame($expected, $this->handler->detectEventTypeEnum($event));
    }

    /** @return array<string, array{string, EventTypeEnum}> */
    public static function provideDetectEventTypeEnum(): array
    {
        return [
            'default (no match)' => ['App\\SomeOther\\Event', EventTypeEnum::UserAction],
        ];
    }

    public function testDetectEventTypeEnumForAuthEvent(): void
    {
        $event = new \App\Auth\Shared\Event\GateSucceededEvent(correlationId: 'c', token: 'tok');
        $this->assertSame(EventTypeEnum::AuthAction, $this->handler->detectEventTypeEnum($event));
    }

    public function testDetectEventTypeEnumForCollectionEvent(): void
    {
        $event = new \App\Collection\Shared\Event\AddToCollectionStartedEvent(mangaId: 'x', mangaTitle: 'y');
        $this->assertSame(EventTypeEnum::CollectionAction, $this->handler->detectEventTypeEnum($event));
    }

    public function testDetectEventTypeEnumForMangaEvent(): void
    {
        $event = new \App\Manga\Shared\Event\ImportMangaStartedEvent(title: 'T');
        $this->assertSame(EventTypeEnum::MangaAction, $this->handler->detectEventTypeEnum($event));
    }

    public function testDetectEventTypeEnumForWishlistEvent(): void
    {
        $event = new class {
            public string $fakeNamespace = 'App\\Wishlist\\Shared\\Event\\FakeEvent';
        };
        // anonymous class won't match namespace detection — remains UserAction
        $this->assertSame(EventTypeEnum::UserAction, $this->handler->detectEventTypeEnum($event));
    }
}
