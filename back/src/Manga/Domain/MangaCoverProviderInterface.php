<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface MangaCoverProviderInterface
{
    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto;

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto;
}
