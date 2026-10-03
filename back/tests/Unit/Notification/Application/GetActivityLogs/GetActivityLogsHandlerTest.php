<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application\GetActivityLogs;

use App\Notification\Application\GetActivityLogs\GetActivityLogsHandler;
use App\Notification\Application\GetActivityLogs\GetActivityLogsQuery;
use App\Notification\Domain\ActivityLog;
use App\Notification\Domain\ActivityLogRepositoryInterface;
use App\Notification\Domain\EventTypeEnum;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetActivityLogsHandlerTest extends TestCase
{
    public function testPassesOnlyTheFiltersThatAreSet(): void
    {
        $log = new ActivityLog(id: 'log-1', eventType: EventTypeEnum::CollectionAction, sourceName: '');
        $log->markSuccess();

        $repository = $this->createMock(ActivityLogRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findPaginated')
            ->with(2, 10, $this->callback(function (array $filters): bool {
                $this->assertSame(['eventType', 'from', 'search'], array_keys($filters));
                $this->assertSame('collection_action', $filters['eventType']);
                $this->assertInstanceOf(DateTimeImmutable::class, $filters['from']);
                $this->assertSame('rating', $filters['search']);

                return true;
            }))
            ->willReturn(['items' => [$log], 'total' => 11]);

        $page = (new GetActivityLogsHandler($repository))(new GetActivityLogsQuery(
            eventType: 'collection_action',
            from: '2026-09-01T00:00:00+00:00',
            to: 'not a date',
            search: 'rating',
            page: 2,
            limit: 10,
        ));

        $this->assertSame([$log->toArray()], $page['items']);
        $this->assertSame(11, $page['total']);
        $this->assertSame(2, $page['page']);
        $this->assertSame(10, $page['limit']);
    }

    public function testWithoutFiltersTheWholeJournalIsPaged(): void
    {
        $repository = $this->createMock(ActivityLogRepositoryInterface::class);
        $repository->expects($this->once())->method('findPaginated')->with(1, 50, [])->willReturn(['items' => [], 'total' => 0]);

        $page = (new GetActivityLogsHandler($repository))(new GetActivityLogsQuery());

        $this->assertSame([], $page['items']);
        $this->assertSame(0, $page['total']);
    }
}
