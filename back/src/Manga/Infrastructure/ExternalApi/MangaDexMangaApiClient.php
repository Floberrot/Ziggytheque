<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaCoverProviderInterface;
use App\Manga\Domain\MangaVolumeCoverDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class MangaDexMangaApiClient implements MangaCoverProviderInterface
{
    private const string PREFIX_LOGGER = 'MANGADEX : ';
    private const string UPLOADS_BASE_URL = 'https://uploads.mangadex.org/covers';
    private const int COVER_PAGE_SIZE = 100;
    // Safety cap on cover pagination (~5 pages) for very long series (e.g. One Piece, 100+ volumes).
    private const int COVER_MAX_OFFSET = 500;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
    ) {
    }

    public function findByIsbn(Isbn $isbn): ?MangaVolumeCoverDto
    {
        // MangaDex does not expose ISBN-based search
        return null;
    }

    public function findByContext(
        string $mangaTitle,
        ?string $edition,
        int $volumeNumber,
        string $language = 'fr',
    ): ?MangaVolumeCoverDto {
        $searchTitle = $this->cleanTitle($mangaTitle, $edition);

        $this->logger->info(self::PREFIX_LOGGER . 'find by context; BEGIN.', [
            'title' => $mangaTitle,
            'search_title' => $searchTitle,
            'edition' => $edition,
            'volume' => $volumeNumber,
            'language' => $language,
        ]);

        try {
            $mangaId = $this->searchMangaId($searchTitle);

            if ($mangaId === null) {
                $this->logger->info(
                    self::PREFIX_LOGGER . 'find by context; NO MANGA FOUND.',
                    ['title' => $searchTitle],
                );
                return null;
            }

            $coverDto = $this->findVolumeCover($mangaId, $volumeNumber, $language);

            if ($coverDto === null) {
                $this->logger->info(self::PREFIX_LOGGER . 'find by context; NO COVER FOUND.', [
                    'title' => $searchTitle,
                    'manga_id' => $mangaId,
                    'volume' => $volumeNumber,
                ]);
            }

            return $coverDto;
        } catch (Throwable $exception) {
            $this->logger->info(self::PREFIX_LOGGER . 'find by context; ERROR.', [
                'title' => $searchTitle,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Strips volume markers ("tome 1", "vol. 2", "t. 3") and the French edition
     * label from the query so MangaDex matches on the bare series title only.
     * MangaDex indexes Japanese/English series titles, so "One Piece tome 1
     * édition originale" must become "One Piece" to match.
     */
    private function cleanTitle(string $title, ?string $edition): string
    {
        $cleaned = $title;

        if ($edition !== null && $edition !== '') {
            $cleaned = str_ireplace($edition, ' ', $cleaned);
        }

        $cleaned = preg_replace('/\b(?:tomes?|volumes?|vol\.?|t\.)\s*\d+/iu', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);

        return $cleaned !== '' ? $cleaned : trim($title);
    }

    private function searchMangaId(string $mangaTitle): ?string
    {
        // No availableTranslatedLanguage filter: we only need the series' cover art,
        // not its scanlations. Filtering by language excludes series whose chapters
        // were removed (e.g. One Piece), even though their cover art is still hosted.
        $response = $this->httpClient->request('GET', $this->baseUrl . '/manga', [
            'query' => [
                'title' => $mangaTitle,
                'limit' => 5,
            ],
        ]);

        $data = $response->toArray();
        $results = $data['data'] ?? [];

        return $results[0]['id'] ?? null;
    }

    private function findVolumeCover(string $mangaId, int $volumeNumber, string $language): ?MangaVolumeCoverDto
    {
        $targetVolume = (string) $volumeNumber;

        /** @var array<int, array{locale: ?string, url: string}> $matches */
        $matches = [];
        $offset = 0;

        // Covers are NOT filtered by locale: MangaDex volume covers are almost
        // always the original Japanese art (locale "ja"), which is exactly the
        // physical cover we want. We collect every cover for the volume across
        // locales, then prefer the requested language, then Japanese, then any.
        do {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/cover', [
                'query' => [
                    'manga[]' => $mangaId,
                    'limit' => self::COVER_PAGE_SIZE,
                    'offset' => $offset,
                    'order[volume]' => 'asc',
                ],
            ]);

            $data = $response->toArray();
            $covers = $data['data'] ?? [];
            $total = (int) ($data['total'] ?? 0);

            foreach ($covers as $cover) {
                $attributes = $cover['attributes'] ?? [];

                if (($attributes['volume'] ?? null) !== $targetVolume) {
                    continue;
                }

                $fileName = $attributes['fileName'] ?? null;
                if (!is_string($fileName) || $fileName === '') {
                    continue;
                }

                $locale = $attributes['locale'] ?? null;
                $matches[] = [
                    'locale' => is_string($locale) ? $locale : null,
                    'url' => sprintf('%s/%s/%s', self::UPLOADS_BASE_URL, $mangaId, $fileName),
                ];
            }

            // Covers are volume-ascending, so once the target volume is seen we have them all.
            if ($matches !== []) {
                break;
            }

            $offset += self::COVER_PAGE_SIZE;
        } while ($offset < $total && $offset < self::COVER_MAX_OFFSET);

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            fn (array $left, array $right): int =>
                $this->localeRank($left['locale'], $language) <=> $this->localeRank($right['locale'], $language),
        );

        $this->logger->info(self::PREFIX_LOGGER . 'find by context; FOUND.', [
            'manga_id' => $mangaId,
            'volume' => $volumeNumber,
            'locale' => $matches[0]['locale'],
        ]);

        return new MangaVolumeCoverDto(
            coverUrl: $matches[0]['url'],
            spineUrl: null,
            isbn: null,
            source: 'mangadex',
        );
    }

    /** Lower is better: requested language → Japanese original → any other → unknown. */
    private function localeRank(?string $locale, string $preferred): int
    {
        return match (true) {
            $locale === $preferred => 0,
            $locale === 'ja' => 1,
            $locale !== null => 2,
            default => 3,
        };
    }
}
