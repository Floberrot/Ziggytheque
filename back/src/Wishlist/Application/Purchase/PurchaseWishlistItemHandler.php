<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Purchase;

use App\Collection\Application\Add\AddToCollectionCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseWishlistItemHandler
{
    public function __construct(
        private WishlistRepositoryInterface $repository,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(PurchaseWishlistItemCommand $command): void
    {
        $item = $this->repository->findById($command->id);

        if ($item === null) {
            throw new NotFoundException('WishlistItem', $command->id);
        }

        $item->isPurchased = true;
        $this->repository->save($item);

        $this->commandBus->dispatch(new AddToCollectionCommand($item->manga->id));
    }
}
