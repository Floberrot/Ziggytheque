<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\Get;

use App\Collection\Application\Get\GetCollectionHandler;
use App\Collection\Application\Get\GetCollectionQuery;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use PHPUnit\Framework\TestCase;

class GetCollectionHandlerTest extends TestCase
{
    public function testReturnsMappedArrays(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$entry]);

        $handler = new GetCollectionHandler($repository);
        $result = $handler(new GetCollectionQuery());

        $this->assertCount(1, $result);
        $this->assertSame('ce-1', $result[0]['id']);
    }

    public function testReturnsEmptyArrayWhenNoEntries(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);

        $handler = new GetCollectionHandler($repository);
        $result = $handler(new GetCollectionQuery());

        $this->assertSame([], $result);
    }
}
