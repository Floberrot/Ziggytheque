<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\GetDetail;

use App\Collection\Application\GetDetail\GetCollectionDetailHandler;
use App\Collection\Application\GetDetail\GetCollectionDetailQuery;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class GetCollectionDetailHandlerTest extends TestCase
{
    public function testReturnsDetailArrayWhenFound(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->with('ce-1')->willReturn($entry);

        $handler = new GetCollectionDetailHandler($repository);
        $result = $handler(new GetCollectionDetailQuery('ce-1'));

        $this->assertSame('ce-1', $result['id']);
        $this->assertArrayHasKey('volumes', $result);
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new GetCollectionDetailHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new GetCollectionDetailQuery('missing'));
    }
}
