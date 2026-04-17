<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Add;

use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Application\Add\AddWishlistItemCommand;
use App\Wishlist\Application\Add\AddWishlistItemHandler;
use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use PHPUnit\Framework\TestCase;

class AddWishlistItemHandlerTest extends TestCase
{
    public function testCreatesAndSavesWishlistItem(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn($manga);

        $wishlistRepo = $this->createMock(WishlistRepositoryInterface::class);
        $wishlistRepo->expects($this->once())->method('save')->with($this->isInstanceOf(WishlistItem::class));

        $handler = new AddWishlistItemHandler($wishlistRepo, $mangaRepo);
        $id = $handler(new AddWishlistItemCommand('m-1'));

        $this->assertNotEmpty($id);
    }

    public function testThrowsWhenMangaNotFound(): void
    {
        $mangaRepo = $this->createMock(MangaRepositoryInterface::class);
        $mangaRepo->method('findById')->willReturn(null);
        $wishlistRepo = $this->createMock(WishlistRepositoryInterface::class);

        $handler = new AddWishlistItemHandler($wishlistRepo, $mangaRepo);

        $this->expectException(NotFoundException::class);
        $handler(new AddWishlistItemCommand('missing'));
    }
}
