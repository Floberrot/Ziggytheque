<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\EditionContext;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;

final readonly class NullMangaCoverApiClient implements MangaCoverProviderInterface
{
    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        return null;
    }

    public function findByContext(EditionContext $context, int $volumeNumber): ?MangaVolumeCoverDto
    {
        return null;
    }
}
