<?php

declare(strict_types=1);

namespace App\Collection\Domain;

/** What adding volumes from the catalogue changed in the user's collection. */
final readonly class CatalogueRegistration
{
    /**
     * @param list<int>          $addedNumbers        tomes newly marked as owned
     * @param list<int>          $alreadyOwnedNumbers tomes that were already owned
     * @param array<int, string> $volumeEntryIds      tome number → volume entry id, for the tomes asked
     */
    public function __construct(
        public string $collectionEntryId,
        public string $mangaId,
        public bool $seriesCreated,
        public bool $entryCreated,
        public int $totalVolumes,
        public array $addedNumbers,
        public array $alreadyOwnedNumbers,
        public array $volumeEntryIds,
    ) {
    }

    /** True when every tome asked was already owned — nothing new entered the collection. */
    public function addedNothing(): bool
    {
        return $this->addedNumbers === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'collectionEntryId'   => $this->collectionEntryId,
            'mangaId'             => $this->mangaId,
            'seriesCreated'       => $this->seriesCreated,
            'entryCreated'        => $this->entryCreated,
            'totalVolumes'        => $this->totalVolumes,
            'addedNumbers'        => $this->addedNumbers,
            'alreadyOwnedNumbers' => $this->alreadyOwnedNumbers,
            'volumeEntryIds'      => (object) $this->volumeEntryIds,
        ];
    }
}
