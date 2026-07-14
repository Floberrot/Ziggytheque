<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\GetActivityLogs;

use App\Notification\Application\GetActivityLogs\GetActivityLogsQuery;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

final class GetActivityLogsQueryTest extends TestCase
{
    public function testDefaults(): void
    {
        $query = new GetActivityLogsQuery();

        $this->assertSame(1, $query->page);
        $this->assertSame(50, $query->limit);
        $this->assertNull($query->eventType);
        $this->assertNull($query->status);
        $this->assertNull($query->collectionEntryId);
        $this->assertNull($query->ownerId);
        $this->assertNull($query->from);
        $this->assertNull($query->to);
        $this->assertNull($query->search);
    }

    public function testParsesAtomDates(): void
    {
        $query = new GetActivityLogsQuery(
            from: '2026-07-01T00:00:00+00:00',
            to: '2026-07-10T23:59:59+02:00',
        );

        $this->assertInstanceOf(DateTimeImmutable::class, $query->from);
        $this->assertInstanceOf(DateTimeImmutable::class, $query->to);
        $this->assertSame('2026-07-01T00:00:00+00:00', $query->from->format(DateTimeInterface::ATOM));
        $this->assertSame('2026-07-10T23:59:59+02:00', $query->to->format(DateTimeInterface::ATOM));
    }

    public function testIgnoresInvalidDates(): void
    {
        $query = new GetActivityLogsQuery(from: 'not-a-date', to: '');

        $this->assertNull($query->from);
        $this->assertNull($query->to);
    }

    public function testCarriesFilters(): void
    {
        $query = new GetActivityLogsQuery(
            eventType: 'http_error',
            status: 'error',
            collectionEntryId: 'ce-1',
            ownerId: 'user-1',
            search: 'share',
            page: 3,
            limit: 25,
        );

        $this->assertSame('http_error', $query->eventType);
        $this->assertSame('error', $query->status);
        $this->assertSame('ce-1', $query->collectionEntryId);
        $this->assertSame('user-1', $query->ownerId);
        $this->assertSame('share', $query->search);
        $this->assertSame(3, $query->page);
        $this->assertSame(25, $query->limit);
    }
}
