<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MultiSourceCoverProviderInterface
{
    /**
     * Returns every cover found for the ISBN across all underlying sources
     * (instead of stopping at the first hit), so the caller can present the
     * user with a grouped choice rather than a single best match.
     *
     * @return list<MangaVolumeCoverDto>
     */
    public function findAllByIsbn(Isbn $isbn): array;

    /**
     * Returns every cover found for a title + volume context across all
     * underlying context sources (MangaDex, Google Books, …), merged into one
     * list so the user can pick between sources — the title-search counterpart
     * of {@see findAllByIsbn()}.
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
