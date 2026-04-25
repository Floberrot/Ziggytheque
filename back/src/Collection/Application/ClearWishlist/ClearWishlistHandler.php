<?php

declare(strict_types=1);

namespace App\Collection\Application\ClearWishlist;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Shared\Event\ClearWishlistFailedEvent;
use App\Collection\Shared\Event\ClearWishlistStartedEvent;
use App\Collection\Shared\Event\ClearWishlistSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ClearWishlistHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ClearWishlistCommand $command): void
    {
        $started = new ClearWishlistStartedEvent(
            collectionEntryId: $command->collectionEntryId,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->repository->findById($command->collectionEntryId);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
            }

            foreach ($entry->volumeEntries as $volumeEntry) {
                /** @var VolumeEntry $volumeEntry */
                $volumeEntry->isWished = false;
            }

            $this->repository->save($entry);

            $this->eventBus->publish(new ClearWishlistSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new ClearWishlistFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
