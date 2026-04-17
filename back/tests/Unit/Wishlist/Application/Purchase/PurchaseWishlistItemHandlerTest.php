<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Application\Purchase;

use App\Collection\Application\Add\AddToCollectionCommand;
use App\Manga\Domain\Manga;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Application\Purchase\PurchaseWishlistItemCommand;
use App\Wishlist\Application\Purchase\PurchaseWishlistItemHandler;
use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use PHPUnit\Framework\TestCase;

class PurchaseWishlistItemHandlerTest extends TestCase
{
    public function testMarksPurchasedAndDispatchesAddToCollection(): void
    {
        $manga = new Manga('m-1', 'Test', 'Ed', 'fr');
        $item = new WishlistItem('wi-1', $manga);

        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findById')->willReturn($item);
        $repository->expects($this->once())->method('save');

        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(AddToCollectionCommand::class));

        $handler = new PurchaseWishlistItemHandler($repository, $commandBus);
        $handler(new PurchaseWishlistItemCommand('wi-1'));

        $this->assertTrue($item->isPurchased);
    }

    public function testThrowsWhenItemNotFound(): void
    {
        $repository = $this->createMock(WishlistRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $commandBus = $this->createMock(CommandBusInterface::class);

        $handler = new PurchaseWishlistItemHandler($repository, $commandBus);

        $this->expectException(NotFoundException::class);
        $handler(new PurchaseWishlistItemCommand('missing'));
    }
}
