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
}
