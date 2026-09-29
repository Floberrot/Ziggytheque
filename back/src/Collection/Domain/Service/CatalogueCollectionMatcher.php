<?php

declare(strict_types=1);

namespace App\Collection\Domain\Service;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Manga\Domain\Catalogue\CatalogueEdition;
use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Manga;
use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Service\PublisherNormalizer;

/**
 * Links catalogue series to what is already stored: the stored series matching an
 * edition identity, and the current user's owned tomes of it.
 */
final readonly class CatalogueCollectionMatcher
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private CollectionRepositoryInterface $collectionRepository,
        private PublisherNormalizer $publisherNormalizer,
    ) {
    }

    public function findSeries(EditionIdentity $identity): ?Manga
    {
        foreach ($this->mangaRepository->findByTitles([$identity->workTitle]) as $manga) {
            if (EditionIdentity::ofManga($manga)->matches($identity, $this->publisherNormalizer)) {
                return $manga;
            }
        }

        return null;
    }

    /**
     * Each edition as an array, plus `collection`: the user's entry id and owned tome
     * numbers when the series is already in the collection, null otherwise.
     *
     * @param  list<CatalogueEdition> $editions
     * @return list<array<string, mixed>>
     */
    public function describe(array $editions): array
    {
        $storedSeries = $this->mangaRepository->findByTitles(
            array_map(static fn (CatalogueEdition $edition): string => $edition->workTitle, $editions),
        );

        /** @var array<string, CollectionEntry> $entriesByMangaId */
        $entriesByMangaId = [];
        $mangaIds = array_map(static fn (Manga $manga): string => $manga->id, $storedSeries);
        foreach ($this->collectionRepository->findByMangaIds($mangaIds) as $entry) {
            $entriesByMangaId[$entry->manga->id] = $entry;
        }

        return array_map(
            fn (CatalogueEdition $edition): array => array_merge($edition->toArray(), [
                'collection' => $this->collectionStatus($edition, $storedSeries, $entriesByMangaId),
            ]),
            $editions,
        );
    }

    /**
     * @param  list<Manga>                     $storedSeries
     * @param  array<string, CollectionEntry>  $entriesByMangaId
     * @return array{entryId: string, ownedNumbers: list<int>}|null
     */
    private function collectionStatus(CatalogueEdition $edition, array $storedSeries, array $entriesByMangaId): ?array
    {
        foreach ($storedSeries as $manga) {
            if (!EditionIdentity::ofManga($manga)->matches($edition->identity(), $this->publisherNormalizer)) {
                continue;
            }

            $entry = $entriesByMangaId[$manga->id] ?? null;
            if ($entry === null) {
                return null;
            }

            $ownedNumbers = [];
            foreach ($entry->volumeEntries as $volumeEntry) {
                if ($volumeEntry->isOwned) {
                    $ownedNumbers[] = $volumeEntry->volume->number;
                }
            }
            sort($ownedNumbers);

            return ['entryId' => $entry->id, 'ownedNumbers' => $ownedNumbers];
        }

        return null;
    }
}
