<?php

declare(strict_types=1);

namespace App\Manga\Domain\Service;

use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;

/**
 * Fills the cover that catalogues leave blank. BnF never returns a visual, so most
 * French editions arrive cover-less even though we hold their ISBN — this resolves
 * that ISBN through the existing cover cascade (BnF → Open Library → Google → …).
 *
 * Runs after grouping, so the number of lookups equals the number of deduplicated
 * editions; a hard cap bounds the worst case regardless.
 */
final readonly class EditionCoverBackfiller
{
    private const int MAX_LOOKUPS = 30;

    public function __construct(private MangaCoverProviderInterface $coverProvider)
    {
    }

    /**
     * @param  list<ExternalEditionDto> $editions
     * @return list<ExternalEditionDto>
     */
    public function backfill(array $editions): array
    {
        $lookups = 0;

        foreach ($editions as $index => $edition) {
            if ($edition->coverUrl !== null) {
                continue;
            }

            if ($lookups >= self::MAX_LOOKUPS) {
                break;
            }

            $lookups++;
            $cover = $this->resolveCover($edition);
            if ($cover !== null) {
                $editions[$index] = $edition->withCoverUrl($cover->coverUrl);
            }
        }

        return $editions;
    }

    /**
     * ISBN is the accurate key, so it wins when present. Editions that arrive without
     * one — frequent for BnF partial records and non-French catalogues — fall back to a
     * title + edition + language context lookup so they still get a cover.
     */
    private function resolveCover(ExternalEditionDto $edition): ?MangaVolumeCoverDto
    {
        if ($edition->isbnSample !== null) {
            $isbn = Isbn::tryFrom($edition->isbnSample);
            if ($isbn !== null) {
                return $this->coverProvider->findByIsbn($isbn);
            }
        }

        return $this->coverProvider->findByContext($edition->workTitle, $edition->editionLine, 1, $edition->language);
    }
}
