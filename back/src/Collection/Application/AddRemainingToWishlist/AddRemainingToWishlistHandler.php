<?php

declare(strict_types=1);

namespace App\Collection\Application\AddRemainingToWishlist;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Shared\Event\AddRemainingToWishlistFailedEvent;
use App\Collection\Shared\Event\AddRemainingToWishlistStartedEvent;
use App\Collection\Shared\Event\AddRemainingToWishlistSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddRemainingToWishlistHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddRemainingToWishlistCommand $command): void
    {
        $started = new AddRemainingToWishlistStartedEvent(
            collectionEntryId: $command->collectionEntryId,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->repository->findById($command->collectionEntryId);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
            }

            $addedCount = 0;
            foreach ($entry->volumeEntries as $volumeEntry) {
                /** @var VolumeEntry $volumeEntry */
                if (!$volumeEntry->isOwned && !$volumeEntry->isWished) {
                    $volumeEntry->isWished = true;
                    $addedCount++;
                }
            }

            $this->repository->save($entry);

            $this->eventBus->publish(new AddRemainingToWishlistSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                addedCount: $addedCount,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new AddRemainingToWishlistFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
