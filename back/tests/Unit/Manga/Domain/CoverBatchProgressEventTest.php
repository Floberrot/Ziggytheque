<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Domain;

use App\Manga\Domain\CoverBatchProgressEvent;
use PHPUnit\Framework\TestCase;

final class CoverBatchProgressEventTest extends TestCase
{
    public function testStartedFactory(): void
    {
        $event = CoverBatchProgressEvent::started('batch-1', 5);

        $this->assertSame('batch_started', $event->type);
        $this->assertSame('batch-1', $event->batchId);
        $this->assertSame(5, $event->total);
        $this->assertSame(0, $event->processed);
        $this->assertSame(0, $event->resolved);
        $this->assertSame(0, $event->failed);
        $this->assertSame(0, $event->skipped);
    }

    public function testVolumeResolvedFactory(): void
    {
        $event = CoverBatchProgressEvent::volumeResolved(
            batchId: 'batch-1',
            total: 10,
            processed: 3,
            resolved: 2,
            failed: 1,
            skipped: 0,
            volumeId: 'vol-123',
            volumeNumber: 3,
            coverUrl: 'https://example.com/cover.jpg',
        );

        $this->assertSame('volume_resolved', $event->type);
        $this->assertSame(10, $event->total);
        $this->assertSame(3, $event->processed);
        $this->assertSame(2, $event->resolved);
        $this->assertSame('vol-123', $event->volumeId);
        $this->assertSame(3, $event->volumeNumber);
        $this->assertSame('https://example.com/cover.jpg', $event->coverUrl);
    }

    public function testVolumeFailedFactory(): void
    {
        $event = CoverBatchProgressEvent::volumeFailed(
            batchId: 'batch-1',
            total: 10,
            processed: 2,
            resolved: 1,
            failed: 1,
            skipped: 0,
            volumeId: 'vol-456',
            volumeNumber: 2,
            reason: 'not_found',
        );

        $this->assertSame('volume_failed', $event->type);
        $this->assertSame('vol-456', $event->volumeId);
        $this->assertSame('not_found', $event->reason);
    }

    public function testCompletedFactory(): void
    {
        $event = CoverBatchProgressEvent::completed('batch-1', 10, 7, 2, 1);

        $this->assertSame('batch_completed', $event->type);
        $this->assertSame(10, $event->total);
        $this->assertSame(7, $event->resolved);
        $this->assertSame(2, $event->failed);
        $this->assertSame(1, $event->skipped);
        $this->assertSame(10, $event->processed);
    }

    public function testToArrayIncludesBaseFields(): void
    {
        $event = CoverBatchProgressEvent::started('batch-1', 5);
        $array = $event->toArray();

        $this->assertSame('batch_started', $array['type']);
        $this->assertSame('batch-1', $array['batchId']);
        $this->assertSame(5, $array['total']);
        $this->assertArrayNotHasKey('volumeId', $array);
        $this->assertArrayNotHasKey('coverUrl', $array);
    }

    public function testToArrayIncludesOptionalFieldsWhenSet(): void
    {
        $event = CoverBatchProgressEvent::volumeResolved(
            batchId: 'batch-1',
            total: 5,
            processed: 1,
            resolved: 1,
            failed: 0,
            skipped: 0,
            volumeId: 'vol-1',
            volumeNumber: 1,
            coverUrl: 'https://example.com/cover.jpg',
        );
        $array = $event->toArray();

        $this->assertSame('vol-1', $array['volumeId']);
        $this->assertSame(1, $array['volumeNumber']);
        $this->assertSame('https://example.com/cover.jpg', $array['coverUrl']);
        $this->assertArrayNotHasKey('reason', $array);
    }
}
