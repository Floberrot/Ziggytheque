<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\EditionFormatEnum;
use App\Manga\Domain\EditionProviderInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenLibraryEditionProvider implements EditionProviderInterface
{
    private const string LOG_PREFIX = 'OPEN_LIBRARY EDITIONS : ';

    /** Open Library splits one manga across several Work records — merge up to 3 of them. */
    private const int MAX_WORKS = 3;

    private const array LANGUAGE_MAP = [
        '/languages/fre' => 'fr',
        '/languages/eng' => 'en',
        '/languages/jpn' => 'ja',
        '/languages/ger' => 'de',
        '/languages/spa' => 'es',
        '/languages/ita' => 'it',
        '/languages/por' => 'pt',
        '/languages/dut' => 'nl',
        '/languages/kor' => 'ko',
        '/languages/chi' => 'zh',
        '/languages/pol' => 'pl',
        '/languages/rus' => 'ru',
    ];

    private const array COUNTRY_MAP = [
        'fr' => 'FR',
        'en' => 'US',
        'ja' => 'JP',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
        'pt' => 'PT',
        'nl' => 'NL',
        'ko' => 'KR',
        'zh' => 'CN',
        'pl' => 'PL',
        'ru' => 'RU',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $userAgent,
        private LoggerInterface $logger,
        private EditionLineExtractor $lineExtractor,
        private EditionRelevanceFilter $relevanceFilter,
    ) {
    }

    public function findEditions(string $workTitle, ?string $author, ?string $language): array
    {
        $this->logger->info(self::LOG_PREFIX . 'findEditions; BEGIN.', ['title' => $workTitle]);

        try {
            return $this->doFindEditions($workTitle, $author, $language);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'findEditions; ERROR.', [
                'title' => $workTitle,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<ExternalEditionDto> */
    private function doFindEditions(string $workTitle, ?string $author, ?string $language): array
    {
        $workKeys = $this->resolveWorkKeys($workTitle, $author);
        if ($workKeys === []) {
            $this->logger->info(self::LOG_PREFIX . 'findEditions; no work found.', ['title' => $workTitle]);

            return [];
        }

        /** @var array<string, true> $seenKeys */
        $seenKeys = [];
        $editions = [];
        foreach ($workKeys as $workKey) {
            foreach ($this->fetchEditions($workKey, $workTitle, $language) as $edition) {
                $dedupeKey = $edition->externalId ?? $edition->isbnSample;
                if ($dedupeKey !== null) {
                    if (isset($seenKeys[$dedupeKey])) {
                        continue;
                    }
                    $seenKeys[$dedupeKey] = true;
                }
                $editions[] = $edition;
            }
        }

        return $editions;
    }

    /**
     * The searched work can be split across several Open Library Work records (the main
     * run, a deluxe re-release, an artbook…). Every doc whose title matches the searched
     * title is a candidate; author-matching docs are preferred, and up to
     * {@see self::MAX_WORKS} works contribute their editions.
     *
     * @return list<string>
     */
    private function resolveWorkKeys(string $workTitle, ?string $author): array
    {
        $url = sprintf(
            '%s/search.json?q=%s&fields=key,title,author_name,edition_count&limit=5',
            $this->baseUrl,
            rawurlencode($workTitle),
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->userAgent],
        ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        /** @var array{docs?: list<array{key: string, title: string, author_name?: list<string>}>} $data */
        $data = json_decode($response->getContent(), true);
        $docs = $data['docs'] ?? [];

        if ($docs === []) {
            return [];
        }

        $authorMatches = [];
        $titleMatches  = [];
        foreach ($docs as $doc) {
            if (!$this->titleMatches($workTitle, $doc['title'])) {
                continue;
            }

            if ($author !== null && $author !== '' && $this->hasAuthor($doc['author_name'] ?? [], $author)) {
                $authorMatches[] = $doc['key'];
                continue;
            }

            $titleMatches[] = $doc['key'];
        }

        $candidates = array_merge($authorMatches, $titleMatches);
        if ($candidates === []) {
            $candidates = [$docs[0]['key']];
        }

        return array_slice(array_values(array_unique($candidates)), 0, self::MAX_WORKS);
    }

    /** @param list<string> $authorNames */
    private function hasAuthor(array $authorNames, string $author): bool
    {
        foreach ($authorNames as $authorName) {
            if (stripos($authorName, $author) !== false) {
                return true;
            }
        }

        return false;
    }

    private function titleMatches(string $workTitle, string $docTitle): bool
    {
        $foldedWork = mb_strtolower(trim($workTitle));
        $foldedDoc  = mb_strtolower(trim($docTitle));

        if ($foldedWork === '' || $foldedDoc === '') {
            return false;
        }

        return str_contains($foldedDoc, $foldedWork) || str_contains($foldedWork, $foldedDoc);
    }

    /** @return list<ExternalEditionDto> */
    private function fetchEditions(string $workKey, string $workTitle, ?string $language): array
    {
        $url = sprintf('%s%s/editions.json?limit=500', $this->baseUrl, $workKey);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => $this->userAgent],
        ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        /** @var array{entries?: list<array<string, mixed>>} $data */
        $data = json_decode($response->getContent(), true);
        $entries = $data['entries'] ?? [];

        $editions = [];
        foreach ($entries as $entry) {
            $edition = $this->buildEditionDto($entry, $workTitle, $language);
            if ($edition !== null) {
                $editions[] = $edition;
            }
        }

        $this->logger->info(self::LOG_PREFIX . 'fetchEditions; done.', [
            'work'  => $workKey,
            'count' => count($editions),
        ]);

        return $editions;
    }

    /** @param array<string, mixed> $entry */
    private function buildEditionDto(array $entry, string $workTitle, ?string $language): ?ExternalEditionDto
    {
        $langKey  = ($entry['languages'][0]['key'] ?? null);
        $langCode = $langKey !== null ? (self::LANGUAGE_MAP[$langKey] ?? null) : null;

        // Single-country search: keep only that language, including editions whose
        // language Open Library left untagged (otherwise they leak in as English).
        if ($language !== null && $langCode !== $language) {
            return null;
        }

        $publishers = $entry['publishers'] ?? [];
        $publisher  = $publishers !== [] ? (string) ($publishers[0] ?? '') : null;
        $publisher  = ($publisher !== '' && $publisher !== null) ? $publisher : null;

        $entryTitle   = (string) ($entry['title'] ?? '');
        $titleToCheck = $entryTitle !== '' ? $entryTitle : $workTitle;
        if (!$this->relevanceFilter->isRelevant($workTitle, $titleToCheck, $publisher)) {
            return null;
        }
        $editionLine = $this->lineExtractor->extract($entryTitle);

        $isbn13 = ($entry['isbn_13'][0] ?? null) ? Isbn::tryFrom((string) $entry['isbn_13'][0]) : null;
        $isbn10 = ($entry['isbn_10'][0] ?? null) ? Isbn::tryFrom((string) $entry['isbn_10'][0]) : null;
        $isbn   = $isbn13 ?? $isbn10;

        $physicalFormat = (string) ($entry['physical_format'] ?? '');
        $format         = EditionFormatEnum::fromRawLabel($physicalFormat !== '' ? $physicalFormat : null);

        // ?default=false → 404 (not a blank placeholder) when the cover is missing.
        $coverIds  = $entry['covers'] ?? [];
        $coverUrl  = $coverIds !== [] && (int) $coverIds[0] > 0
            ? sprintf('https://covers.openlibrary.org/b/id/%s-L.jpg?default=false', $coverIds[0])
            : null;

        $country    = $langCode !== null ? self::COUNTRY_MAP[$langCode] : null;
        $editionKey = ($entry['key'] ?? null);
        $label      = $editionLine !== null
            ? sprintf('%s — %s', $publisher ?? $workTitle, $editionLine)
            : ($publisher ?? $workTitle);

        return new ExternalEditionDto(
            workTitle:    $workTitle,
            editionLabel: $label,
            publisher:    $publisher,
            language:     $langCode ?? 'en',
            country:      $country,
            format:       $format,
            volumeCount:  null,
            isbnSample:   $isbn?->value,
            coverUrl:     $coverUrl,
            source:       'open_library',
            externalId:   $editionKey,
            editionLine:  $editionLine,
        );
    }
}
