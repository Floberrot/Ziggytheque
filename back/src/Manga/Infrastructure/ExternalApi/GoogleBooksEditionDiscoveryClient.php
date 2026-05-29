<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Service\EditionGrouper;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * @phpstan-import-type RawVolume from EditionGrouper
 */
final readonly class GoogleBooksEditionDiscoveryClient implements EditionDiscoveryInterface
{
    private const string BASE_URL = 'https://www.googleapis.com/books/v1';
    private const string PREFIX_LOGGER = 'GOOGLE_BOOKS_EDITIONS : ';
    private const int MAX_PAGES = 2;
    private const int PAGE_SIZE = 20;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private EditionGrouper $editionGrouper,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ExternalEditionDto[]
     */
    public function discoverEditions(string $workTitle, Country $country): array
    {
        $language = $country->language();

        $this->logger->info(self::PREFIX_LOGGER . 'discoverEditions; BEGIN.', [
            'title'    => $workTitle,
            'language' => $language,
        ]);

        $rawVolumes = [];

        for ($pageIndex = 0; $pageIndex < self::MAX_PAGES; $pageIndex++) {
            $pageItems = $this->fetchPage($workTitle, $language, $pageIndex);
            $rawVolumes = array_merge($rawVolumes, $pageItems);
        }

        $this->logger->info(self::PREFIX_LOGGER . 'discoverEditions; raw volumes fetched.', [
            'count' => count($rawVolumes),
        ]);

        return $this->editionGrouper->group($rawVolumes, $language, 'google_books');
    }

    /**
     * @return array<int, RawVolume>
     */
    private function fetchPage(string $workTitle, string $language, int $pageIndex): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
                'query' => [
                    'q'            => $workTitle . '+manga',
                    'printType'    => 'books',
                    'langRestrict' => $language,
                    'maxResults'   => self::PAGE_SIZE,
                    'startIndex'   => $pageIndex * self::PAGE_SIZE,
                    'orderBy'      => 'relevance',
                    'key'          => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();
        } catch (Throwable $exception) {
            $this->logger->warning(self::PREFIX_LOGGER . 'fetchPage failed.', [
                'page'    => $pageIndex,
                'message' => $exception->getMessage(),
            ]);
            return [];
        }

        if (empty($data['items'])) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $item) => $this->normalizeItem($item, $language),
            $data['items'],
        )));
    }

    /**
     * @param array<string, mixed> $item
     * @return RawVolume|null
     */
    private function normalizeItem(array $item, string $language): ?array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];
        $title = $volumeInfo['title'] ?? null;

        if ($title === null) {
            return null;
        }

        // langRestrict is loosely enforced for manga; drop items whose declared
        // language disagrees with the requested one. Items without a language
        // tag are kept (the metadata is simply missing).
        $itemLanguage = $volumeInfo['language'] ?? null;
        if ($itemLanguage !== null && $itemLanguage !== $language) {
            return null;
        }

        $publisher = $volumeInfo['publisher'] ?? null;
        $year = $this->extractYear($volumeInfo['publishedDate'] ?? null);
        $volumeNumber = $this->extractVolumeNumber($volumeInfo);
        $coverUrl = $this->extractCoverUrl($volumeInfo);
        $isbn = $this->extractIsbn($volumeInfo);

        return [
            'publisher'    => $publisher,
            'year'         => $year,
            'volumeNumber' => $volumeNumber,
            'title'        => $title,
            'coverUrl'     => $coverUrl,
            'isbn'         => $isbn,
        ];
    }

    private function extractYear(?string $publishedDate): ?int
    {
        if ($publishedDate === null) {
            return null;
        }

        $year = (int) substr($publishedDate, 0, 4);

        return $year > 0 ? $year : null;
    }

    /** @param array<string, mixed> $volumeInfo */
    private function extractVolumeNumber(array $volumeInfo): ?int
    {
        if (!empty($volumeInfo['seriesInfo']['bookDisplayNumber'])) {
            $number = filter_var($volumeInfo['seriesInfo']['bookDisplayNumber'], FILTER_VALIDATE_INT);
            if ($number !== false) {
                return $number;
            }
        }

        // Try to extract from title (e.g. "Berserk Tome 1", "Berserk T03")
        $title = $volumeInfo['title'] ?? '';
        if (preg_match('/\b(?:tome|vol(?:ume)?|t)[\s.]?(\d+)/i', $title, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /** @param array<string, mixed> $volumeInfo */
    private function extractCoverUrl(array $volumeInfo): ?string
    {
        $imageLinks = $volumeInfo['imageLinks'] ?? [];

        $url = $imageLinks['extraLarge']
            ?? $imageLinks['large']
            ?? $imageLinks['medium']
            ?? $imageLinks['thumbnail']
            ?? $imageLinks['smallThumbnail']
            ?? null;

        if ($url === null) {
            return null;
        }

        $url = str_replace('http://', 'https://', $url);
        return implode('', explode('&edge=curl', $url));
    }

    /** @param array<string, mixed> $volumeInfo */
    private function extractIsbn(array $volumeInfo): ?string
    {
        foreach ($volumeInfo['industryIdentifiers'] ?? [] as $identifier) {
            if (($identifier['type'] ?? '') === 'ISBN_13') {
                return $identifier['identifier'] ?? null;
            }
        }

        foreach ($volumeInfo['industryIdentifiers'] ?? [] as $identifier) {
            if (($identifier['type'] ?? '') === 'ISBN_10') {
                return $identifier['identifier'] ?? null;
            }
        }

        return null;
    }
}
