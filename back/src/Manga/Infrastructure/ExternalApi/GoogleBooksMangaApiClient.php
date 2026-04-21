<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\ExternalVolumeDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class GoogleBooksMangaApiClient implements ExternalApiClientInterface
{
    private const string BASE_URL = 'https://www.googleapis.com/books/v1';
    private const string PREFIX_LOGGER = 'GOOGLE_BOOKS : ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return ExternalMangaDto[]
     */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info(self::PREFIX_LOGGER . 'search by title; BEGIN.', [
            'query' => $query,
            'type' => $type,
            'page' => $page
        ]);

        $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
            'query' => [
                'q' => $query . '+manga',
                'printType' => 'books',
                'maxResults' => 20,
                'startIndex' => ($page - 1) * 20,
                'orderBy' => 'relevance',
                'key' => $this->apiKey,
            ],
        ]);

        $this->logger->info(self::PREFIX_LOGGER . 'search by title; REQUESTED.', [
            'query' => $query,
            'type' => $type,
            'page' => $page,
            'response' => $response
        ]);

        try {
            $data = $response->toArray();
        } catch (Throwable) {
            $this->logger->info(self::PREFIX_LOGGER . 'search by title; EMPTY.', [
                'query' => $query,
                'type' => $type,
                'page' => $page,
                'response' => $response
            ]);
            return [];
        }

        if (empty($data['items'])) {
            $this->logger->info(self::PREFIX_LOGGER . 'search by title; EMPTY.', [
                'query' => $query,
                'type' => $type,
                'page' => $page,
                'response' => $response
            ]);
            return [];
        }

        return array_values(array_filter(array_map(
            fn(array $item) => $this->mapToDto($item),
            $data['items'],
        )));
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info(self::PREFIX_LOGGER . 'manga by id; BEGIN.', [
            'externalId' => $externalId
        ]);
        $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes/' . $externalId, [
            'query' => ['key' => $this->apiKey],
        ]);

        $this->logger->info(self::PREFIX_LOGGER . 'manga by id; DONE.', [
            'externalId' => $externalId,
            'response' => $response
        ]);
        $data = $response->toArray();

        return $this->mapToDto($data);
    }

    /** @return ExternalVolumeDto[] */
    public function getVolumeCovers(string $externalId): array
    {
        $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: start', ['externalId' => $externalId]);

        try {
            $baseResponse = $this->httpClient->request('GET', self::BASE_URL . '/volumes/' . $externalId, [
                'query' => ['key' => $this->apiKey],
            ]);
            $base = $baseResponse->toArray();
        } catch (Throwable $e) {
            $this->logger->error(self::PREFIX_LOGGER . 'getVolumeCovers: failed to fetch base volume', [
                'externalId' => $externalId, 'error' => $e->getMessage(),
            ]);
            return [];
        }

        $seriesTitle = $base['volumeInfo']['title'] ?? null;
        if ($seriesTitle === null) {
            $this->logger->warning(self::PREFIX_LOGGER . 'getVolumeCovers: no title on base volume', [
                'externalId' => $externalId,
            ]);
            return [];
        }

        // Strip volume suffix to isolate the series name (e.g. "One Piece, Vol. 12" → "One Piece")
        $seriesName = preg_replace('/[,\s]+(vol|tome|t|volume)\.?\s*\d+.*$/i', '', $seriesTitle) ?? $seriesTitle;

        $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: searching series', [
            'seriesName' => $seriesName,
        ]);

        try {
            $searchResponse = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
                'query' => [
                    'q'          => $seriesName . '+manga',
                    'maxResults' => 40,
                    'key'        => $this->apiKey,
                ],
            ]);
            $data = $searchResponse->toArray();
        } catch (Throwable $e) {
            $this->logger->error(self::PREFIX_LOGGER . 'getVolumeCovers: search request failed', [
                'seriesName' => $seriesName, 'error' => $e->getMessage(),
            ]);
            return [];
        }

        $volumes = [];
        foreach ($data['items'] ?? [] as $item) {
            $info      = $item['volumeInfo'] ?? [];
            $title     = $info['title'] ?? '';
            $volumeNum = $this->parseVolumeNumber($title);
            if ($volumeNum === null) {
                continue;
            }

            $coverUrl = $this->extractCoverUrl($info);
            if ($coverUrl === null) {
                continue;
            }

            $releaseDate = null;
            if (!empty($info['publishedDate'])) {
                try {
                    $releaseDate = new \DateTimeImmutable($info['publishedDate']);
                } catch (Throwable) {
                }
            }

            // Keep the first hit per volume number (relevance order)
            if (!isset($volumes[$volumeNum])) {
                $volumes[$volumeNum] = new ExternalVolumeDto(
                    number:      $volumeNum,
                    coverUrl:    $coverUrl,
                    releaseDate: $releaseDate,
                );
            }
        }

        ksort($volumes);
        $result = array_values($volumes);

        $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: done', [
            'externalId' => $externalId,
            'volumes'    => count($result),
        ]);

        return $result;
    }

    private function parseVolumeNumber(string $title): ?int
    {
        // "One Piece, Vol. 12" / "Naruto Tome 7" / "Attack on Titan 3"
        if (preg_match('/(?:vol|tome|t|volume)\.?\s*(\d+)/i', $title, $matches)) {
            return (int) $matches[1];
        }
        // Trailing number: "Dragon Ball Z 5"
        if (preg_match('/[,\s]+(\d{1,3})\s*$/i', $title, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function isManga(array $info): bool
    {
        $mangaKeywords = [
            'manga', 'comic', 'bande dessinée', 'bd',
            'shonen', 'shojo', 'seinen', 'josei', 'manhwa', 'manhua',
        ];

        foreach ($info['categories'] ?? [] as $category) {
            $cat = strtolower($category);
            foreach ($mangaKeywords as $kw) {
                if (str_contains($cat, $kw)) {
                    return true;
                }
            }
        }

        // Accept if no categories — trust the query filter did its job
        return empty($info['categories']);
    }

    /** @param array<string, mixed> $item */
    private function mapToDto(array $item): ?ExternalMangaDto
    {
        $info = $item['volumeInfo'] ?? [];

        $title = $info['title'] ?? null;
        if ($title === null) {
            return null;
        }

        if (!$this->isManga($info)) {
            return null;
        }

        $coverUrl = $this->extractCoverUrl($info);
        if ($coverUrl === null) {
            return null;
        }

        $author = !empty($info['authors']) ? implode(', ', $info['authors']) : null;
        $edition = $info['publisher'] ?? null;
        $language = $info['language'] ?? 'fr';
        $summary = $info['description'] ?? null;
        $genre = $this->extractGenre($info['categories'] ?? []);
        $totalVolumes = $this->extractVolumeNumber($info);

        return new ExternalMangaDto(
            externalId: $item['id'],
            title: $title,
            edition: $edition,
            author: $author,
            summary: $summary,
            coverUrl: $coverUrl,
            genre: $genre,
            language: $language,
            source: 'google',
            totalVolumes: $totalVolumes,
        );
    }

    /** @param array<string, mixed> $info */
    private function extractCoverUrl(array $info): ?string
    {
        $links = $info['imageLinks'] ?? [];

        $url = $links['extraLarge']
            ?? $links['large']
            ?? $links['medium']
            ?? $links['thumbnail']
            ?? $links['smallThumbnail']
            ?? null;

        if ($url === null) {
            return null;
        }

        $url = str_replace('http://', 'https://', $url);
        return implode('', explode('&edge=curl', $url));
    }

    /** @param string[] $categories */
    private function extractGenre(array $categories): ?string
    {
        if (empty($categories)) {
            return null;
        }

        $raw = strtolower($categories[0]);

        return match (true) {
            str_contains($raw, 'shonen') || str_contains($raw, 'shōnen') => 'shonen',
            str_contains($raw, 'shojo') || str_contains($raw, 'shōjo') => 'shojo',
            str_contains($raw, 'seinen') => 'seinen',
            str_contains($raw, 'josei') => 'josei',
            str_contains($raw, 'isekai') => 'isekai',
            str_contains($raw, 'fantasy') || str_contains($raw, 'fantaisie') => 'fantasy',
            str_contains($raw, 'action') => 'action',
            str_contains($raw, 'romance') => 'romance',
            str_contains($raw, 'horror') || str_contains($raw, 'horreur') => 'horror',
            str_contains($raw, 'science') || str_contains($raw, 'sci-fi') => 'sci_fi',
            str_contains($raw, 'sport') => 'sports',
            default => 'other',
        };
    }

    /** @param array<string, mixed> $info */
    private function extractVolumeNumber(array $info): ?int
    {
        if (!empty($info['seriesInfo']['bookDisplayNumber'])) {
            $num = filter_var($info['seriesInfo']['bookDisplayNumber'], FILTER_VALIDATE_INT);
            if ($num !== false) {
                return $num;
            }
        }

        return null;
    }
}
