<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Domain;

use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\EventTypeEnum;
use PHPUnit\Framework\TestCase;

final class ActivityLogTest extends TestCase
{
    public function testConstructionDefaults(): void
    {
        $log = new ActivityLog(
            id: 'log-1',
            eventType: EventTypeEnum::CollectionAction,
            sourceName: 'AddToCollection',
        );

        $this->assertSame('log-1', $log->id);
        $this->assertSame(EventTypeEnum::CollectionAction, $log->eventType);
        $this->assertSame('AddToCollection', $log->sourceName);
        $this->assertSame('running', $log->status);
        $this->assertNull($log->errorMessage);
        $this->assertNull($log->newArticlesCount);
        $this->assertNull($log->metadata);
        $this->assertNull($log->finishedAt);
        $this->assertNull($log->collectionEntry);
    }

    public function testMarkSuccess(): void
    {
        $log = new ActivityLog(id: 'l1', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $log->markSuccess(5, ['feed' => 'manga-news']);

        $this->assertSame('success', $log->status);
        $this->assertSame(5, $log->newArticlesCount);
        $this->assertSame(['feed' => 'manga-news'], $log->metadata);
        $this->assertNotNull($log->finishedAt);
    }

    public function testMarkSuccessWithoutMetadata(): void
    {
        $log = new ActivityLog(id: 'l1', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $log->markSuccess(0);

        $this->assertSame('success', $log->status);
        $this->assertNull($log->metadata);
    }

    public function testMarkSuccessMergesExistingMetadata(): void
    {
        $log           = new ActivityLog(id: 'l1', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $log->metadata = ['existing' => true];
        $log->markSuccess(1, ['new' => 'value']);

        $this->assertSame(['existing' => true, 'new' => 'value'], $log->metadata);
    }

    public function testMarkError(): void
    {
        $log = new ActivityLog(id: 'l1', eventType: EventTypeEnum::HttpError, sourceName: 'rss');
        $log->markError('Connection refused', ['exception_class' => 'RuntimeException']);

        $this->assertSame('error', $log->status);
        $this->assertSame('Connection refused', $log->errorMessage);
        $this->assertSame(['exception_class' => 'RuntimeException'], $log->metadata);
        $this->assertNotNull($log->finishedAt);
    }

    public function testMarkErrorTruncatesLongMessage(): void
    {
        $log     = new ActivityLog(id: 'l1', eventType: EventTypeEnum::WorkerFailure, sourceName: 'worker');
        $longMsg = str_repeat('x', 3000);
        $log->markError($longMsg);

        $this->assertSame(2000, mb_strlen((string) $log->errorMessage));
    }

    public function testToArray(): void
    {
        $log = new ActivityLog(id: 'l1', eventType: EventTypeEnum::AuthAction, sourceName: 'gate');
        $log->markSuccess(2);

        $arr = $log->toArray();

        $this->assertSame('l1', $arr['id']);
        $this->assertSame('auth_action', $arr['eventType']);
        $this->assertSame('gate', $arr['sourceName']);
        $this->assertNull($arr['collectionEntryId']);
        $this->assertSame('success', $arr['status']);
        $this->assertSame(2, $arr['newArticlesCount']);
        $this->assertArrayHasKey('startedAt', $arr);
        $this->assertArrayHasKey('finishedAt', $arr);
        $this->assertIsInt($arr['durationMs']);
    }

    public function testToArrayRunningHasNullDuration(): void
    {
        $log = new ActivityLog(id: 'l1', eventType: EventTypeEnum::RssFetch, sourceName: 'rss');
        $arr = $log->toArray();

        $this->assertSame('running', $arr['status']);
        $this->assertNull($arr['durationMs']);
        $this->assertNull($arr['finishedAt']);
    }
}
