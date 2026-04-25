<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Purchase;

use App\Collection\Application\Add\AddToCollectionCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use App\Wishlist\Shared\Event\PurchaseWishlistItemFailedEvent;
use App\Wishlist\Shared\Event\PurchaseWishlistItemStartedEvent;
use App\Wishlist\Shared\Event\PurchaseWishlistItemSucceededEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseWishlistItemHandler
{
    public function __construct(
        private WishlistRepositoryInterface $repository,
        private CommandBusInterface $commandBus,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(PurchaseWishlistItemCommand $command): void
    {
        $started = new PurchaseWishlistItemStartedEvent(wishlistItemId: $command->id);
        $this->eventBus->publish($started);

        try {
            $item = $this->repository->findById($command->id);

            if ($item === null) {
                throw new NotFoundException('WishlistItem', $command->id);
            }

            $mangaId = $item->manga->id;
            $item->isPurchased = true;
            $this->repository->save($item);

            $this->commandBus->dispatch(new AddToCollectionCommand($mangaId));

            $this->eventBus->publish(new PurchaseWishlistItemSucceededEvent(
                correlationId: $started->correlationId,
                wishlistItemId: $item->id,
                mangaId: $mangaId,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new PurchaseWishlistItemFailedEvent(
                correlationId: $started->correlationId,
                wishlistItemId: $command->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
