<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Application\GetWishlist;

use App\Collection\Application\GetWishlist\GetWishlistHandler;
use App\Collection\Application\GetWishlist\GetWishlistQuery;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Tests\Unit\Collection\Application\CollectedSeries;
use PHPUnit\Framework\TestCase;

final class GetWishlistHandlerTest extends TestCase
{
    /** The wishlist lists each entry with its tomes, so the page can show which are wished. */
    public function testWrapsTheWishedEntriesWithTheirTomes(): void
    {
        $entry = CollectedSeries::withTomes(2);
        CollectedSeries::tome($entry, 2)->isWished = true;
        $query = new GetWishlistQuery(search: 'berserk', page: 2, limit: 20);

        $repository = $this->createMock(CollectionRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findWishedFiltered')
            ->with($query)
            ->willReturn(['items' => [$entry], 'total' => 21]);

        $page = (new GetWishlistHandler($repository))($query);

        $this->assertSame(21, $page['total']);
        $this->assertSame(2, $page['page']);
        $this->assertSame(20, $page['limit']);
        $this->assertSame([$entry->toDetailArray()], $page['items']);
        $this->assertSame([false, true], array_column($page['items'][0]['volumes'], 'isWished'));
    }
}
