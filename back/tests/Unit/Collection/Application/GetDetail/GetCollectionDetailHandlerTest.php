<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\GetDetail;

use App\Collection\Application\GetDetail\GetCollectionDetailHandler;
use App\Collection\Application\GetDetail\GetCollectionDetailQuery;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class GetCollectionDetailHandlerTest extends TestCase
{
    public function testReturnsTheEntryWithItsTomesInOrder(): void
    {
        $entry      = CollectedSeries::withTomes(2);
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn($entry);

        $detail = (new GetCollectionDetailHandler($repository))(new GetCollectionDetailQuery('entry-1'));

        $this->assertSame($entry->toDetailArray(), $detail);
        $this->assertSame([1, 2], array_column($detail['volumes'], 'number'));
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $repository = $this->createStub(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(NotFoundException::class);

        (new GetCollectionDetailHandler($repository))(new GetCollectionDetailQuery('ghost'));
    }
}
