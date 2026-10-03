<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Get;

use App\Collection\Application\Get\GetCollectionHandler;
use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class GetCollectionHandlerTest extends TestCase
{
    public function testWrapsTheFilteredPageInThePaginationEnvelope(): void
    {
        $entry = CollectedSeries::withTomes(1);
        $query = new GetCollectionQuery(search: 'berserk', readingStatus: ReadingStatusEnum::InProgress, page: 3, limit: 5);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findFiltered')
            ->with($query)
            ->willReturn(['items' => [$entry], 'total' => 11]);

        $page = (new GetCollectionHandler($repository))($query);

        $this->assertSame(11, $page['total']);
        $this->assertSame(3, $page['page']);
        $this->assertSame(5, $page['limit']);
        $this->assertSame([$entry->toArray()], $page['items']);
    }
}
