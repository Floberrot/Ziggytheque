<?php

declare(strict_types=1);

namespace App\Collection\Application\SyncVolumes;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Shared\Event\SyncVolumesFailedEvent;
use App\Collection\Shared\Event\SyncVolumesStartedEvent;
use App\Collection\Shared\Event\SyncVolumesSucceededEvent;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SyncVolumesHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(SyncVolumesCommand $command): void
    {
        $started = new SyncVolumesStartedEvent(
            collectionEntryId: $command->collectionEntryId,
        );
        $this->eventBus->publish($started);

        try {
            $entry = $this->collectionRepository->findById($command->collectionEntryId);

            if ($entry === null) {
                throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
            }

            $manga = $entry->manga;

            // The series gets every tome up to the asked one, then the entry tracks them all.
            if ($command->upToVolume !== null && $command->upToVolume > 0) {
                $manga->ensureVolumesUpTo($command->upToVolume);
                $this->mangaRepository->save($manga);
            }

            $addedCount = $entry->trackMissingVolumes();

            $this->collectionRepository->save($entry);

            $this->eventBus->publish(new SyncVolumesSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                addedCount: $addedCount,
            ));
        } catch (Throwable $exception) {
            $this->eventBus->publish(new SyncVolumesFailedEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $command->collectionEntryId,
                error: $exception->getMessage(),
                exceptionClass: $exception::class,
            ));
            throw $exception;
        }
    }
}
