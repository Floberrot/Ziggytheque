<?php

declare(strict_types=1);

namespace App\Manga\Domain;

final readonly class ExternalMangaDto
{
    /**
     * @param ExternalVolumeDto[] $volumes
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $edition,
        public ?string $author,
        public ?string $summary,
        public ?string $coverUrl,
        public ?string $genre,
        public string $language,
        public ?int $totalVolumes = null,
        public array $volumes = [],
        public string $source = 'unknown',
    ) {
    }
}
