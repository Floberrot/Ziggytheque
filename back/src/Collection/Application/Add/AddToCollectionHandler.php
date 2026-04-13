<?php

declare(strict_types=1);

namespace App\Collection\Application\Add;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddToCollectionHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MangaRepositoryInterface $mangaRepository,
    ) {
    }

    public function __invoke(AddToCollectionCommand $command): string
    {
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

        return $entry->id;
    }
}
