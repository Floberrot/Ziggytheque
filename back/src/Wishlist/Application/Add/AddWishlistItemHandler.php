<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Add;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use App\Wishlist\Domain\WishlistItem;
use App\Wishlist\Domain\WishlistRepositoryInterface;
use App\Wishlist\Shared\Event\AddWishlistItemFailedEvent;
use App\Wishlist\Shared\Event\AddWishlistItemStartedEvent;
use App\Wishlist\Shared\Event\AddWishlistItemSucceededEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddWishlistItemHandler
{
    public function __construct(
        private WishlistRepositoryInterface $repository,
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddWishlistItemCommand $command): string
    {
        $started = new AddWishlistItemStartedEvent(mangaId: $command->mangaId);
        $this->eventBus->publish($started);

        try {
            $manga = $this->mangaRepository->findById($command->mangaId);

            if ($manga === null) {
                throw new NotFoundException('Manga', $command->mangaId);
            }

            $item = new WishlistItem(id: Uuid::v4()->toRfc4122(), manga: $manga);
            $this->repository->save($item);

            $this->eventBus->publish(new AddWishlistItemSucceededEvent(
                correlationId: $started->correlationId,
                wishlistItemId: $item->id,
                mangaId: $manga->id,
            ));

            return $item->id;
        } catch (Throwable $e) {
            $this->eventBus->publish(new AddWishlistItemFailedEvent(
                correlationId: $started->correlationId,
                mangaId: $command->mangaId,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
