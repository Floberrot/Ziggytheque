<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\GetWishlist;

use App\Collection\Application\GetWishlist\GetWishlistHandler;
use App\Collection\Application\GetWishlist\GetWishlistQuery;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Manga;
use PHPUnit\Framework\TestCase;

class GetWishlistHandlerTest extends TestCase
{
    public function testReturnsMappedDetailArrays(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $entry = new CollectionEntry('ce-1', $manga);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findWithWishedVolumes')->willReturn([$entry]);

        $handler = new GetWishlistHandler($repository);
        $result = $handler(new GetWishlistQuery());

        $this->assertCount(1, $result);
        $this->assertSame('ce-1', $result[0]['id']);
        $this->assertArrayHasKey('volumes', $result[0]);
    }

    public function testReturnsEmptyWhenNoWishlistEntries(): void
    {
        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->method('findWithWishedVolumes')->willReturn([]);

        $handler = new GetWishlistHandler($repository);
        $result = $handler(new GetWishlistQuery());

        $this->assertSame([], $result);
    }
}
