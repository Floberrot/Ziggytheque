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

final readonly class GoogleBooksEditionProvider implements EditionProviderInterface
{
    private const string BASE_URL   = 'https://www.googleapis.com/books/v1';
    private const string LOG_PREFIX = 'GOOGLE BOOKS EDITIONS : ';

    /** Markets swept when no specific language is requested (broad discovery). */
    private const array DISCOVERY_LOCALES = ['fr', 'en', 'ja', 'de', 'es', 'it'];

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
        private string $apiKey,
        private LoggerInterface $logger,
        private EditionLineExtractor $lineExtractor,
        private EditionRelevanceFilter $relevanceFilter,
    ) {
    }

    public function findEditions(string $workTitle, ?string $author, ?string $language): array
    {
        if ($this->apiKey === '') {
            return [];
        }

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
        // A blank language means "discover everywhere": sweep the main manga markets in
        // one go instead of just French, deduplicating volumes by their Google id.
        $locales = $language !== null ? [$language] : self::DISCOVERY_LOCALES;

        /** @var array<string, ExternalEditionDto> $byId */
        $byId = [];
        $fallbackIndex = 0;
        foreach ($locales as $locale) {
            foreach ($this->fetchLocale($workTitle, $author, $locale) as $edition) {
                // langRestrict only biases Google's results, so a foreign edition can
                // still come back. In single-country mode, keep only the asked language.
                if ($language !== null && $edition->language !== $language) {
                    continue;
                }
                $key = $edition->externalId ?? ('anon-' . $fallbackIndex++);
                $byId[$key] ??= $edition;
            }
        }

        return array_values($byId);
    }

    /** @return list<ExternalEditionDto> */
    private function fetchLocale(string $workTitle, ?string $author, string $language): array
    {
        $query = sprintf('intitle:%s', rawurlencode($workTitle));
        if ($author !== null && $author !== '') {
            $query .= sprintf('+inauthor:%s', rawurlencode($author));
        }

        $url = sprintf(
            '%s/volumes?q=%s&langRestrict=%s&maxResults=40&key=%s',
            self::BASE_URL,
            $query,
            $language,
            $this->apiKey,
        );

        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            $this->logger->info(self::LOG_PREFIX . 'findEditions; NOT 200.', [
                'status'   => $response->getStatusCode(),
                'language' => $language,
            ]);

            return [];
        }

        /** @var array{items?: list<array<string, mixed>>} $data */
        $data  = json_decode($response->getContent(), true);
        $items = $data['items'] ?? [];

        $editions = [];
        foreach ($items as $item) {
            $edition = $this->buildEditionDto($item, $workTitle, $language);
            if ($edition !== null) {
                $editions[] = $edition;
            }
        }

        return $editions;
    }

    /** @param array<string, mixed> $item */
    private function buildEditionDto(array $item, string $workTitle, string $language): ?ExternalEditionDto
    {
        /** @var array<string, mixed> $volumeInfo */
        $volumeInfo = $item['volumeInfo'] ?? [];
        $publisher  = (string) ($volumeInfo['publisher'] ?? '');
        $publisher  = $publisher !== '' ? $publisher : null;

        $volumeTitle = (string) ($volumeInfo['title'] ?? '');
        $subtitle    = (string) ($volumeInfo['subtitle'] ?? '');
        if (!$this->relevanceFilter->isRelevant($volumeTitle !== '' ? $volumeTitle : $workTitle, $publisher)) {
            return null;
        }
        $editionLine = $this->lineExtractor->extract($volumeTitle, $subtitle);

        $langCode = (string) ($volumeInfo['language'] ?? $language);
        $country  = self::COUNTRY_MAP[$langCode] ?? null;

        $isbnSample = null;
        $identifiers = $volumeInfo['industryIdentifiers'] ?? [];
        foreach ($identifiers as $identifier) {
            if (($identifier['type'] ?? '') === 'ISBN_13') {
                $isbn = Isbn::tryFrom((string) ($identifier['identifier'] ?? ''));
                if ($isbn !== null) {
                    $isbnSample = $isbn->value;
                    break;
                }
            }
        }

        $coverUrl = ($volumeInfo['imageLinks']['thumbnail'] ?? null);

        $format   = EditionFormatEnum::fromRawLabel(
            $editionLine ?? (($volumeInfo['printType'] ?? null) ?? ($subtitle !== '' ? $subtitle : null)),
        );

        $externalId = (string) ($item['id'] ?? '');
        $label      = $editionLine !== null
            ? sprintf('%s — %s', $publisher ?? $workTitle, $editionLine)
            : ($publisher ?? $workTitle);

        return new ExternalEditionDto(
            workTitle:    $workTitle,
            editionLabel: $label,
            publisher:    $publisher,
            language:     $langCode,
            country:      $country,
            format:       $format,
            volumeCount:  null,
            isbnSample:   $isbnSample,
            coverUrl:     $coverUrl,
            source:       'google_books',
            externalId:   $externalId !== '' ? $externalId : null,
            editionLine:  $editionLine,
        );
    }
}
