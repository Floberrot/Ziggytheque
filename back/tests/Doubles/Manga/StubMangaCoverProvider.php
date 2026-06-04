<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Manga;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Domain\MultiSourceCoverProviderInterface;

final class StubMangaCoverProvider implements MangaCoverProviderInterface, MultiSourceCoverProviderInterface
{
    /** @var array<string, list<MangaVolumeCoverDto>> */
    private array $isbnResults = [];

    /** @var list<MangaVolumeCoverDto> */
    private array $contextResults = [];

    public function registerIsbn(string $isbnValue, MangaVolumeCoverDto $dto): void
    {
        $this->isbnResults[Isbn::fromString($isbnValue)->value][] = $dto;
    }

    public function registerContext(MangaVolumeCoverDto $dto): void
    {
        $this->contextResults[] = $dto;
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        return $this->isbnResults[$isbn->value][0] ?? null;
    }

    public function findAllByIsbn(Isbn $isbn): array
    {
        return $this->isbnResults[$isbn->value] ?? [];
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        return $this->contextResults[0] ?? null;
    }

    public function findAllByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): array {
        return $this->contextResults;
    }
}
