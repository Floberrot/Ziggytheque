<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Exception\CatalogueUnavailableException;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Google Books, French editions only — the fallback for what the BnF does not have
 * yet (a release still going through legal deposit). Only books with an ISBN are kept.
 */
final readonly class GoogleBooksCatalogue implements CatalogueInterface
{
    private const string LOG_PREFIX = 'GOOGLE BOOKS CATALOGUE : ';
    private const string BASE_URL = 'https://www.googleapis.com/books/v1/volumes';
    private const int PAGE_SIZE = 40;

    /** @var list<string> */
    private const array PLACEHOLDER_API_KEYS = ['', 'change_me', 'changeme'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private LoggerInterface $logger,
        private CatalogueTitleParser $titleParser,
        private EditionRelevanceFilter $relevanceFilter,
    ) {
    }

    public function searchByTitle(string $title): array
    {
        return $this->search('intitle:' . $title, $title);
    }

    public function searchByAuthor(string $author): array
    {
        return $this->search('inauthor:' . $author, null);
    }

    public function findByIsbn(Isbn $isbn): array
    {
        return $this->search('isbn:' . $isbn->value, null);
    }

    /** @return list<CatalogueRecord> */
    private function search(string $googleQuery, ?string $searchedTitle): array
    {
        if (in_array(mb_strtolower($this->apiKey), self::PLACEHOLDER_API_KEYS, true)) {
            return [];
        }

        $this->logger->info(self::LOG_PREFIX . 'search; BEGIN.', ['query' => $googleQuery]);

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'q'            => $googleQuery,
                    'langRestrict' => 'fr',
                    'printType'    => 'books',
                    'maxResults'   => self::PAGE_SIZE,
                    'key'          => $this->apiKey,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning(self::LOG_PREFIX . 'search; NOT 200.', ['status' => $response->getStatusCode()]);

                throw new CatalogueUnavailableException('Google Books');
            }

            /** @var array{items?: list<array<string, mixed>>}|null $payload */
            $payload = json_decode($response->getContent(), true);
        } catch (CatalogueUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'search; ERROR.', [
                'query' => $googleQuery,
                'error' => $exception->getMessage(),
            ]);

            throw new CatalogueUnavailableException('Google Books');
        }

        $records = [];
        foreach ($payload['items'] ?? [] as $item) {
            $record = $this->parseItem($item, $searchedTitle);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /** @param array<string, mixed> $item */
    private function parseItem(array $item, ?string $searchedTitle): ?CatalogueRecord
    {
        /** @var array<string, mixed> $volumeInfo */
        $volumeInfo = is_array($item['volumeInfo'] ?? null) ? $item['volumeInfo'] : [];

        $title = is_string($volumeInfo['title'] ?? null) ? $volumeInfo['title'] : '';
        if ($title === '' || ($volumeInfo['language'] ?? 'fr') !== 'fr') {
            return null;
        }

        $subtitle  = is_string($volumeInfo['subtitle'] ?? null) ? $volumeInfo['subtitle'] : null;
        $publisher = is_string($volumeInfo['publisher'] ?? null) && $volumeInfo['publisher'] !== ''
            ? $volumeInfo['publisher']
            : null;

        $isbn = $this->extractIsbn($volumeInfo);
        if ($isbn === null) {
            return null;
        }

        $categories = array_values(array_filter(
            is_array($volumeInfo['categories'] ?? null) ? $volumeInfo['categories'] : [],
            is_string(...),
        ));
        $fullTitle = $subtitle !== null ? $title . ' ' . $subtitle : $title;
        if (!$this->relevanceFilter->isRelevant($searchedTitle ?? $fullTitle, $fullTitle, $publisher, $categories)) {
            return null;
        }

        $parsedTitle = $this->titleParser->parse($title, $subtitle);
        $authors     = is_array($volumeInfo['authors'] ?? null) ? $volumeInfo['authors'] : [];
        $author      = is_string($authors[0] ?? null) ? $authors[0] : null;

        return new CatalogueRecord(
            workTitle: $parsedTitle->workTitle,
            headQualifier: $parsedTitle->headQualifier,
            volumeNumber: $parsedTitle->volumeNumber,
            trailingQualifier: $parsedTitle->trailingQualifier,
            publisher: $publisher,
            author: $author,
            isbn: $isbn,
            coverUrl: $this->coverUrl($volumeInfo),
            source: 'google_books',
        );
    }

    /**
     * The ISBN-13, else the ISBN-10 converted.
     *
     * @param array<string, mixed> $volumeInfo
     */
    private function extractIsbn(array $volumeInfo): ?Isbn
    {
        $identifiers = is_array($volumeInfo['industryIdentifiers'] ?? null) ? $volumeInfo['industryIdentifiers'] : [];

        $byType = [];
        foreach ($identifiers as $identifier) {
            $type  = is_array($identifier) ? ($identifier['type'] ?? null) : null;
            $value = is_array($identifier) ? ($identifier['identifier'] ?? null) : null;
            if (is_string($type) && is_string($value)) {
                $byType[$type] ??= $value;
            }
        }

        return Isbn::tryFrom($byType['ISBN_13'] ?? null) ?? Isbn::tryFrom($byType['ISBN_10'] ?? null);
    }

    /** @param array<string, mixed> $volumeInfo */
    private function coverUrl(array $volumeInfo): ?string
    {
        $imageLinks = is_array($volumeInfo['imageLinks'] ?? null) ? $volumeInfo['imageLinks'] : [];
        $thumbnail  = $imageLinks['thumbnail'] ?? null;

        return is_string($thumbnail) ? str_replace('http://', 'https://', $thumbnail) : null;
    }
}
