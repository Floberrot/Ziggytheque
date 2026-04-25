<?php

declare(strict_types=1);

namespace App\Collection\Application\Add;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Shared\Event\AddToCollectionFailedEvent;
use App\Collection\Shared\Event\AddToCollectionStartedEvent;
use App\Collection\Shared\Event\AddToCollectionSucceededEvent;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Throwable;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddToCollectionHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MangaRepositoryInterface $mangaRepository,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddToCollectionCommand $command): string
    {
        $started = new AddToCollectionStartedEvent(
            mangaId: $command->mangaId,
            mangaTitle: '',
        );
        $this->eventBus->publish($started);

        try {
            $manga = $this->mangaRepository->findById($command->mangaId);

            if ($manga === null) {
                throw new NotFoundException('Manga', $command->mangaId);
            }

            $entry = new CollectionEntry(
                id: Uuid::v4()->toRfc4122(),
                manga: $manga,
            );

            foreach ($manga->volumes as $volume) {
                $entry->volumeEntries->add(new VolumeEntry(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    volume: $volume,
                ));
            }

            $this->collectionRepository->save($entry);

            $this->eventBus->publish(new AddToCollectionSucceededEvent(
                correlationId: $started->correlationId,
                collectionEntryId: $entry->id,
                mangaId: $manga->id,
                mangaTitle: $manga->title,
            ));

            return $entry->id;
        } catch (Throwable $e) {
            $this->eventBus->publish(new AddToCollectionFailedEvent(
                correlationId: $started->correlationId,
                mangaId: $command->mangaId,
                mangaTitle: '',
                error: $e->getMessage(),
                exceptionClass: $e::class,
            ));
            throw $e;
        }
    }
}
