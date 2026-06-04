<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MultiContextCoverProviderInterface
{
    /**
     * Returns every candidate cover a source can offer for a title + volume
     * context (possibly empty), instead of only its single best match — so the
     * caller can present the user with several covers to choose from, each
     * tagged with its origin.
     *
     * @return list<MangaVolumeCoverDto>
     */
    public function findAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): array;
}
