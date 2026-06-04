<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiContextCoverProviderInterface;

final readonly class NullMangaCoverApiClient implements
    MangaCoverProviderInterface,
    MultiContextCoverProviderInterface
{
    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        return null;
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        return null;
    }

    public function findAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): array {
        return [];
    }
}
