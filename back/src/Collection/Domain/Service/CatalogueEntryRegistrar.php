<?php

declare(strict_types=1);

namespace App\Collection\Domain\Service;

use App\Auth\Domain\User;
use App\Collection\Domain\CatalogueRegistration;
use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Manga-first addition: the user picks tomes, the whole series follows.
 *
 * Finds the series (or creates it with every known tome), starts the user's entry if
 * needed, then marks the picked tomes as owned. The other tomes stay untracked.
 */
final readonly class CatalogueEntryRegistrar
{
    public function __construct(
        private CatalogueCollectionMatcher $matcher,
        private MangaRepositoryInterface $mangaRepository,
        private CollectionRepositoryInterface $collectionRepository,
    ) {
    }

    /** @param list<int> $ownedNumbers */
    public function register(CatalogueEdition $edition, array $ownedNumbers, ?User $owner): CatalogueRegistration
    {
        $manga = $this->matcher->findSeries($edition->identity());
        $seriesCreated = $manga === null;
        $manga ??= new Manga(
            id: Uuid::v4()->toRfc4122(),
            title: $edition->workTitle,
            edition: $edition->publisher,
            language: 'fr',
            author: $edition->author,
            coverUrl: $edition->coverUrl,
            specialEdition: $edition->specialEdition,
        );

        $this->completeSeries($manga, $edition, $ownedNumbers);
        $this->mangaRepository->save($manga);

        $entry = $this->collectionRepository->findByMangaId($manga->id);
        $entryCreated = $entry === null;
        $entry ??= new CollectionEntry(id: Uuid::v4()->toRfc4122(), manga: $manga, owner: $owner);
        $entry->trackMissingVolumes();

        $addedNumbers = [];
        $alreadyOwnedNumbers = [];
        $volumeEntryIds = [];
        foreach (array_values(array_unique($ownedNumbers)) as $number) {
            $volumeEntry = $entry->volumeEntryForNumber($number);
            if ($volumeEntry === null) {
                continue;
            }

            $volumeEntryIds[$number] = $volumeEntry->id;
            if ($volumeEntry->isOwned) {
                $alreadyOwnedNumbers[] = $number;
                continue;
            }

            $volumeEntry->markOwned();
            $addedNumbers[] = $number;
        }

        $entry->refreshReadingStatus();
        $this->collectionRepository->save($entry);

        return new CatalogueRegistration(
            collectionEntryId: $entry->id,
            mangaId: $manga->id,
            seriesCreated: $seriesCreated,
            entryCreated: $entryCreated,
            totalVolumes: $manga->volumes->count(),
            addedNumbers: $addedNumbers,
            alreadyOwnedNumbers: $alreadyOwnedNumbers,
            volumeEntryIds: $volumeEntryIds,
        );
    }

    /**
     * Every tome up to the highest known one exists; catalogue ISBNs and covers fill
     * the gaps of stored tomes but never overwrite what the user set.
     *
     * @param list<int> $ownedNumbers
     */
    private function completeSeries(Manga $manga, CatalogueEdition $edition, array $ownedNumbers): void
    {
        $manga->ensureVolumesUpTo(max([$edition->volumeCount, ...$ownedNumbers]));
        $manga->coverUrl ??= $edition->coverUrl;
        $manga->author ??= $edition->author;

        foreach ($edition->volumes as $catalogueVolume) {
            $volume = $manga->volumeByNumber($catalogueVolume->number);
            if ($volume === null) {
                continue;
            }

            $volume->isbn ??= $catalogueVolume->isbn;
            $volume->coverUrl ??= $catalogueVolume->coverUrl;
        }
    }
}
