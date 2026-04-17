<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Remove;

use App\Manga\Domain\Manga;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Application\Remove\RemoveWishlistItemCommand;
use App\Wishlist\Application\Remove\RemoveWishlistItemHandler;
use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RemoveWishlistItemHandlerTest extends TestCase
{
    public function testDeletesExistingItem(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $item = new WishlistItem('wi-1', $manga);

        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findById')->with('wi-1')->willReturn($item);
        $repository->expects($this->once())->method('delete')->with($item);

        $handler = new RemoveWishlistItemHandler($repository);
        $handler(new RemoveWishlistItemCommand('wi-1'));
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new RemoveWishlistItemHandler($repository);

        $this->expectException(NotFoundException::class);
        $handler(new RemoveWishlistItemCommand('missing'));
    }
}
