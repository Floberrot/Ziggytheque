<?php

declare(strict_types=1);

namespace App\Manga\Domain\Catalogue;

use App\Manga\Domain\EditionIdentity;
use App\Manga\Domain\Isbn;

/**
 * A series as a collector sees it — one work, at one publisher, in one edition
 * (standard or special) — rebuilt from the individual volume records a catalogue
 * returned.
 */
final readonly class CatalogueEdition
{
    /** @param list<CatalogueVolume> $volumes sorted by number */
    public function __construct(
        public string $workTitle,
        public ?string $publisher,
        public ?string $specialEdition,
        public ?string $author,
        public ?string $coverUrl,
        public int $volumeCount,
        public array $volumes,
    ) {
    }

    public function identity(): EditionIdentity
    {
        return new EditionIdentity($this->workTitle, $this->publisher, $this->specialEdition);
    }

    public function volumeByIsbn(Isbn $isbn): ?CatalogueVolume
    {
        foreach ($this->volumes as $volume) {
            if ($volume->isbn !== null && $volume->isbn->equals($isbn)) {
                return $volume;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'workTitle'      => $this->workTitle,
            'publisher'      => $this->publisher,
            'specialEdition' => $this->specialEdition,
            'author'         => $this->author,
            'coverUrl'       => $this->coverUrl,
            'volumeCount'    => $this->volumeCount,
            'volumes'        => array_map(
                static fn (CatalogueVolume $volume): array => $volume->toArray(),
                $this->volumes,
            ),
        ];
    }
}
