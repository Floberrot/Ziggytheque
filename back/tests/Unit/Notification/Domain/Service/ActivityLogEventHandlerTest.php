<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain\Service;

use App\Auth\Domain\User;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogOwnerResolverInterface;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use App\Notification\Domain\Service\ActivityLogEventHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class ActivityLogEventHandlerTest extends TestCase
{
    private ActivityLogRepositoryInterface&MockObject $logRepo;
    private CollectionRepositoryInterface&MockObject $collectionRepo;
    private ActivityLogOwnerResolverInterface&MockObject $ownerResolver;
    private ActivityLogEventHandler $handler;

    protected function setUp(): void
    {
        $this->logRepo        = $this->createMock(ActivityLogRepositoryInterface::class);
        $this->collectionRepo = $this->createMock(CollectionRepositoryInterface::class);
        $this->ownerResolver  = $this->createMock(ActivityLogOwnerResolverInterface::class);
        $this->handler        = new ActivityLogEventHandler(
            $this->logRepo,
            $this->collectionRepo,
            $this->ownerResolver,
        );
    }

    private function makeUser(string $id = 'user-1', string $email = 'user@test.local'): User
    {
        return new User(
            id: $id,
            email: $email,
            passwordHash: 'hash',
            displayName: 'Test User',
        );
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

    public function testHandleStartedEventAttributesCurrentUserAsOwner(): void
    {
        $owner = $this->makeUser();
        $this->ownerResolver->method('currentOwner')->willReturn($owner);

        $event = new class {
            public string $correlationId = 'corr-owner';
            public string $sourceName    = 'AddToCollection';
        };

        $this->logRepo->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ActivityLog $log) use ($owner): bool {
                $this->assertSame($owner, $log->owner);
                return true;
            }));

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

    public function testHandleSucceededEventAttributesOwnerFromUserId(): void
    {
        $log   = new ActivityLog(id: 'corr-uid', eventType: EventTypeEnum::AuthAction, sourceName: 'login');
        $owner = $this->makeUser('user-42');

        $event = new class {
            public string $correlationId = 'corr-uid';
            public string $userId        = 'user-42';
            public string $email         = 'user@test.local';
        };

        $this->logRepo->method('findById')->willReturn($log);
        $this->ownerResolver->expects($this->once())
            ->method('findById')
            ->with('user-42')
            ->willReturn($owner);
        $this->logRepo->expects($this->once())->method('save');

        $this->handler->handleSucceededEvent($event);

        $this->assertSame($owner, $log->owner);
        $this->assertSame(['email' => 'user@test.local'], $log->metadata);
    }

    public function testHandleSucceededEventKeepsExistingOwner(): void
    {
        $existingOwner = $this->makeUser('user-1');
        $log           = new ActivityLog(
            id: 'corr-keep',
            eventType: EventTypeEnum::AuthAction,
            sourceName: 'gate',
            owner: $existingOwner,
        );

        $event = new class {
            public string $correlationId = 'corr-keep';
            public string $userId        = 'user-other';
        };

        $this->logRepo->method('findById')->willReturn($log);
        $this->ownerResolver->expects($this->never())->method('findById');

        $this->handler->handleSucceededEvent($event);

        $this->assertSame($existingOwner, $log->owner);
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
        $event = new class {
        };

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
        $this->assertSame(['exception_class' => 'RuntimeException'], $log->metadata);
    }

    public function testHandleFailedEventKeepsSafeMetadata(): void
    {
        $log   = new ActivityLog(id: 'corr-f2', eventType: EventTypeEnum::AuthAction, sourceName: 'login');
        $event = new class {
            public string $correlationId  = 'corr-f2';
            public string $error          = 'Invalid credentials';
            public string $exceptionClass = 'RuntimeException';
            public string $email          = 'attempt@test.local';
        };

        $this->logRepo->method('findById')->willReturn($log);

        $this->handler->handleFailedEvent($event);

        $this->assertSame(
            ['email' => 'attempt@test.local', 'exception_class' => 'RuntimeException'],
            $log->metadata,
        );
    }

    public function testHandleFailedEventSkipsIfNoCorrelationId(): void
    {
        $event = new class {
        };
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

    // ── Metadata sanitization ────────────────────────────────────────────────

    public function testMetadataExcludesSensitivePropertyNames(): void
    {
        $log   = new ActivityLog(id: 'corr-s1', eventType: EventTypeEnum::AuthAction, sourceName: 'gate');
        $event = new class {
            public string $correlationId  = 'corr-s1';
            public string $token          = 'jwt-token-value';
            public string $accessToken    = 'another-secret';
            public string $password       = 'hunter2';
            public string $clientSecret   = 'shhh';
            public string $jwtValue       = 'eyJ...';
            public string $authorization  = 'Bearer xyz';
            public string $apiKey         = 'key-123';
            public string $email          = 'safe@test.local';
        };

        $this->logRepo->method('findById')->willReturn($log);

        $this->handler->handleSucceededEvent($event);

        $this->assertSame(['email' => 'safe@test.local'], $log->metadata);
    }

    public function testMetadataFromGateSucceededEventNeverContainsToken(): void
    {
        $log   = new ActivityLog(id: 'corr-gate', eventType: EventTypeEnum::AuthAction, sourceName: 'gate');
        $event = new \App\Auth\Shared\Event\GateSucceededEvent(correlationId: 'corr-gate', userId: 'user-1');

        $this->logRepo->method('findById')->willReturn($log);

        $this->handler->handleSucceededEvent($event);

        $metadataJson = json_encode($log->metadata);
        $this->assertIsString($metadataJson);
        $this->assertStringNotContainsStringIgnoringCase('token', $metadataJson);
    }

    public function testMetadataKeepsOnlyScalarsAndArraysOfScalars(): void
    {
        $log   = new ActivityLog(id: 'corr-s2', eventType: EventTypeEnum::UserAction, sourceName: 'test');
        $event = new class {
            public string $correlationId = 'corr-s2';
            public string $name          = 'value';
            public int $number           = 7;
            public float $ratio          = 0.5;
            public bool $flag            = true;
            public object $service;
            /** @var array<string, mixed> */
            public array $mixedArray;

            public function __construct()
            {
                $this->service    = new \stdClass();
                $this->mixedArray = ['keep' => 'yes', 'drop' => new \stdClass(), 'secretKey' => 'no'];
            }
        };

        $this->logRepo->method('findById')->willReturn($log);

        $this->handler->handleSucceededEvent($event);

        $this->assertSame(
            [
                'name'       => 'value',
                'number'     => 7,
                'ratio'      => 0.5,
                'flag'       => true,
                'mixedArray' => ['keep' => 'yes'],
            ],
            $log->metadata,
        );
    }

    public function testMetadataTruncatesLongStrings(): void
    {
        $log   = new ActivityLog(id: 'corr-s3', eventType: EventTypeEnum::UserAction, sourceName: 'test');
        $event = new class {
            public string $correlationId = 'corr-s3';
            public string $longText;

            public function __construct()
            {
                $this->longText = str_repeat('a', 800);
            }
        };

        $this->logRepo->method('findById')->willReturn($log);

        $this->handler->handleSucceededEvent($event);

        $this->assertIsArray($log->metadata);
        $this->assertIsString($log->metadata['longText']);
        $this->assertSame(500, mb_strlen($log->metadata['longText']));
    }

    // ── Event type detection ─────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('provideDetectEventTypeEnum')]
    public function testDetectEventTypeEnum(string $namespace, EventTypeEnum $expected): void
    {
        // Create an anonymous class that fakes a namespace via class alias
        $event = new class ($namespace) {
            public function __construct(public readonly string $fakeNamespace)
            {
            }
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
        $event = new \App\Auth\Shared\Event\GateSucceededEvent(correlationId: 'c', userId: 'user-1');
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

    public function testDetectEventTypeEnumForWishlistEventInCollectionModule(): void
    {
        $event = new \App\Collection\Shared\Event\ClearWishlistStartedEvent(collectionEntryId: 'ce-1');
        $this->assertSame(EventTypeEnum::WishlistAction, $this->handler->detectEventTypeEnum($event));
    }

    public function testDetectEventTypeEnumForAddRemainingToWishlistEvent(): void
    {
        $event = new \App\Collection\Shared\Event\AddRemainingToWishlistStartedEvent(collectionEntryId: 'ce-1');
        $this->assertSame(EventTypeEnum::WishlistAction, $this->handler->detectEventTypeEnum($event));
    }
}
