<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Remove;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use App\Wishlist\Shared\Event\RemoveWishlistItemFailedEvent;
use App\Wishlist\Shared\Event\RemoveWishlistItemStartedEvent;
use App\Wishlist\Shared\Event\RemoveWishlistItemSucceededEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveWishlistItemHandler
{
    public function __construct(
        private WishlistRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(RemoveWishlistItemCommand $command): void
    {
        $started = new RemoveWishlistItemStartedEvent(wishlistItemId: $command->id);
        $this->eventBus->publish($started);

        try {
            $item = $this->repository->findById($command->id);

            if ($item === null) {
                throw new NotFoundException('WishlistItem', $command->id);
            }

            $this->repository->delete($item);

            $this->eventBus->publish(new RemoveWishlistItemSucceededEvent(
                correlationId: $started->correlationId,
                wishlistItemId: $item->id,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new RemoveWishlistItemFailedEvent(
                correlationId: $started->correlationId,
                wishlistItemId: $command->id,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
