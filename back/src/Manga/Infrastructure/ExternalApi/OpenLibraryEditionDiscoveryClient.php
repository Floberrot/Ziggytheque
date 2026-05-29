<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class OpenLibraryEditionDiscoveryClient implements EditionDiscoveryInterface
{
    private const string PREFIX_LOGGER = 'OPEN_LIBRARY_EDITIONS : ';
    private const string COVERS_BASE_URL = 'https://covers.openlibrary.org';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
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

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/search.json', [
                'query' => [
                    'q'      => $workTitle . ' manga',
                    'fields' => 'key,title,publisher,first_publish_year,number_of_pages_median,isbn,language',
                    'limit'  => 20,
                ],
            ]);

            $data = $response->toArray();
        } catch (Throwable $exception) {
            $this->logger->warning(self::PREFIX_LOGGER . 'discoverEditions failed.', [
                'message' => $exception->getMessage(),
            ]);
            return [];
        }

        $docs = $data['docs'] ?? [];

        if (empty($docs)) {
            return [];
        }

        /** @var array<string, ExternalEditionDto> $grouped */
        $grouped = [];

        foreach ($docs as $doc) {
            $docLanguages = $doc['language'] ?? [];

            // Filter by language (Open Library uses language codes: 'fre' for French, 'eng' for English)
            $targetCode = $this->toOpenLibraryLanguageCode($language);
            if (!empty($docLanguages) && !in_array($targetCode, $docLanguages, true)) {
                continue;
            }

            $publishers = $doc['publisher'] ?? [];
            if (empty($publishers)) {
                continue;
            }

            $publisher = (string) ($publishers[0] ?? '');
            if ($publisher === '') {
                continue;
            }

            $groupKey = strtolower($publisher);

            if (isset($grouped[$groupKey])) {
                continue;
            }

            $year = isset($doc['first_publish_year']) ? (int) $doc['first_publish_year'] : null;
            $isbns = $doc['isbn'] ?? [];
            $sampleIsbn = !empty($isbns) ? (string) $isbns[0] : null;
            $coverUrl = $sampleIsbn !== null ? $this->buildCoverUrl($sampleIsbn) : null;

            $grouped[$groupKey] = new ExternalEditionDto(
                publisher: $publisher,
                editionLabel: null,
                year: $year,
                language: $language,
                coverUrl: $coverUrl,
                volumeCount: null,
                sampleIsbn: $sampleIsbn,
                source: 'open_library',
            );
        }

        return array_values($grouped);
    }

    private function buildCoverUrl(string $isbn): string
    {
        return sprintf('%s/b/isbn/%s-L.jpg', self::COVERS_BASE_URL, $isbn);
    }

    private function toOpenLibraryLanguageCode(string $languageCode): string
    {
        return match ($languageCode) {
            'fr' => 'fre',
            'en' => 'eng',
            'de' => 'ger',
            'es' => 'spa',
            'ja' => 'jpn',
            default => $languageCode,
        };
    }
}
