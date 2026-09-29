<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\GenreEnum;
use App\Manga\Domain\Manga;
use App\Manga\Domain\TextFold;

/**
 * Fills what a series is missing (genre, summary, author) from an external match —
 * only from a result carrying the very same title, and never over a value already set.
 */
final readonly class MangaEnricher
{
    public function needsEnrichment(Manga $manga): bool
    {
        return $manga->genre === null || $manga->summary === null || $manga->author === null;
    }

    /**
     * @param  array<ExternalMangaDto> $candidates
     * @return bool whether the series changed
     */
    public function enrich(Manga $manga, array $candidates): bool
    {
        $match = $this->sameTitle($manga, $candidates);
        if ($match === null) {
            return false;
        }

        $genre = GenreEnum::tryFrom($match->genre ?? '');
        $changed = false;

        if ($manga->genre === null && $genre !== null) {
            $manga->genre = $genre;
            $changed = true;
        }

        if ($manga->summary === null && $match->summary !== null && $match->summary !== '') {
            $manga->summary = $match->summary;
            $changed = true;
        }

        if ($manga->author === null && $match->author !== null && $match->author !== '') {
            $manga->author = $match->author;
            $changed = true;
        }

        return $changed;
    }

    /** @param array<ExternalMangaDto> $candidates */
    private function sameTitle(Manga $manga, array $candidates): ?ExternalMangaDto
    {
        $foldedTitle = TextFold::fold($manga->title);

        foreach ($candidates as $candidate) {
            if (TextFold::fold($candidate->title) === $foldedTitle) {
                return $candidate;
            }
        }

        return null;
    }
}
