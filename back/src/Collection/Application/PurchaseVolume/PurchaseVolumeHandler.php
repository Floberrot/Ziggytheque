<?php

declare(strict_types=1);

namespace App\Collection\Application\PurchaseVolume;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Shared\Event\PurchaseVolumeFailedEvent;
use App\Collection\Shared\Event\PurchaseVolumeStartedEvent;
use App\Collection\Shared\Event\PurchaseVolumeSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseVolumeHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(PurchaseVolumeCommand $command): void
    {
        $started = new PurchaseVolumeStartedEvent(
            collectionEntryId: $command->collectionEntryId,
            volumeEntryId: $command->volumeEntryId,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->repository->findById($command->collectionEntryId);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
            }

            $volumeEntry = $entry->volumeEntries
                ->filter(fn (VolumeEntry $ve) => $ve->id === $command->volumeEntryId)
                ->first();

            if ($volumeEntry === false) {
                throw new NotFoundException('VolumeEntry', $command->volumeEntryId);
            }

            $volumeEntry->isOwned = true;
            $volumeEntry->isWished = false;

            $this->repository->save($entry);

            $this->eventBus->publish(new PurchaseVolumeSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                volumeEntryId: $volumeEntry->id,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new PurchaseVolumeFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                volumeEntryId: $command->volumeEntryId,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
