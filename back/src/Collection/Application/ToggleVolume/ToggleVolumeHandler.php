<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolume;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Domain\VolumeToggleFieldEnum;
use App\Collection\Shared\Event\ToggleVolumeFailedEvent;
use App\Collection\Shared\Event\ToggleVolumeStartedEvent;
use App\Collection\Shared\Event\ToggleVolumeSucceededEvent;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleVolumeHandler
{
    public function __construct(
        private CollectionRepositoryInterface $repository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(ToggleVolumeCommand $command): void
    {
        $started = new ToggleVolumeStartedEvent(
            collectionEntryId: $command->collectionEntryId,
            volumeEntryId: $command->volumeEntryId,
            field: $command->field->value,
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

            if ($command->field === VolumeToggleFieldEnum::IsOwned) {
                if ($volumeEntry->isOwned) {
                    $volumeEntry->isOwned = false;
                } else {
                    $volumeEntry->markOwned();
                }
            } else {
                match ($command->field) {
                    VolumeToggleFieldEnum::IsRead      => $volumeEntry->isRead      = !$volumeEntry->isRead,
                    VolumeToggleFieldEnum::IsWished    => $volumeEntry->isWished    = !$volumeEntry->isWished,
                    VolumeToggleFieldEnum::IsAnnounced => $volumeEntry->isAnnounced = !$volumeEntry->isAnnounced,
                };
            }

            $entry->refreshReadingStatus();

            $this->repository->save($entry);

            $this->eventBus->publish(new ToggleVolumeSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                volumeEntryId: $volumeEntry->id,
                field: $command->field->value,
            ));
        } catch (Throwable $e) {
            $this->eventBus->publish(new ToggleVolumeFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                volumeEntryId: $command->volumeEntryId,
                field: $command->field->value,
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
