<?php

declare(strict_types=1);

namespace App\Collection\Application\SyncVolumes;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SyncVolumesHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private MangaRepositoryInterface $mangaRepository,
    ) {
    }

    public function __invoke(SyncVolumesCommand $command): void
    {
        $entry = $this->collectionRepository->findById($command->collectionEntryId);

        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $manga = $entry->manga;

        // Step 1: if upToVolume is given, create missing Volume placeholders on the Manga
        if ($command->upToVolume !== null && $command->upToVolume > 0) {
            $existingNumbers = $manga->volumes
                ->map(fn (Volume $v) => $v->number)
                ->toArray();

            for ($n = 1; $n <= $command->upToVolume; $n++) {
                if (!in_array($n, $existingNumbers, true)) {
                    $manga->addVolume(new Volume(
                        id: Uuid::v4()->toRfc4122(),
                        manga: $manga,
                        number: $n,
                    ));
                }
            }

            $this->mangaRepository->save($manga);
        }

        // Step 2: create missing VolumeEntries for any Volume not yet tracked
        $trackedVolumeIds = $entry->volumeEntries
            ->map(fn (VolumeEntry $ve) => $ve->volume->id)
            ->toArray();

        foreach ($manga->volumes as $volume) {
            if (!in_array($volume->id, $trackedVolumeIds, true)) {
                $entry->volumeEntries->add(new VolumeEntry(
                    id: Uuid::v4()->toRfc4122(),
                    collectionEntry: $entry,
                    volume: $volume,
                ));
            }
        }

        $this->collectionRepository->save($entry);
    }
}
