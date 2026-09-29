<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Catalogue;

use App\Manga\Domain\Catalogue\CatalogueInterface;
use App\Manga\Domain\Catalogue\CatalogueRecord;
use App\Manga\Domain\Isbn;
use App\Manga\Domain\Service\CatalogueTitleParser;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * BnF SRU catalogue (French legal deposit): every book printed in France lands here,
 * with an ISBD title that states the special edition structurally
 * ("Berserk : prestige. 3"). One request per search — no fan-out.
 */
final readonly class BnfCatalogue implements CatalogueInterface
{
    private const string LOG_PREFIX = 'BNF CATALOGUE : ';
    private const int PAGE_SIZE = 100;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
        private CatalogueTitleParser $titleParser,
        private EditionRelevanceFilter $relevanceFilter,
    ) {
    }

    public function searchByTitle(string $title): array
    {
        return $this->search(sprintf('bib.title all "%s"', $this->escape($title)), $title);
    }

    public function searchByAuthor(string $author): array
    {
        return $this->search(sprintf('bib.author all "%s"', $this->escape($author)), null);
    }

    public function findByIsbn(Isbn $isbn): array
    {
        return $this->search(sprintf('bib.isbn all "%s"', $isbn->value), null);
    }

    /** @return list<CatalogueRecord> */
    private function search(string $cqlQuery, ?string $searchedTitle): array
    {
        $this->logger->info(self::LOG_PREFIX . 'search; BEGIN.', ['query' => $cqlQuery]);

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/api/SRU', [
                'query' => [
                    'version'        => '1.2',
                    'operation'      => 'searchRetrieve',
                    'query'          => $cqlQuery,
                    'recordSchema'   => 'dublincore',
                    'maximumRecords' => (string) self::PAGE_SIZE,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->info(self::LOG_PREFIX . 'search; NOT 200.', ['status' => $response->getStatusCode()]);

                return [];
            }

            return $this->parseResponse($response->getContent(), $searchedTitle);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'search; ERROR.', [
                'query' => $cqlQuery,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<CatalogueRecord> */
    private function parseResponse(string $xmlContent, ?string $searchedTitle): array
    {
        if ($xmlContent === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($xmlContent);
        $xml->registerXPathNamespace('srw', 'http://www.loc.gov/zing/srw/');

        /** @var array<SimpleXMLElement>|false $recordNodes */
        $recordNodes = $xml->xpath('//srw:record/srw:recordData');
        if ($recordNodes === false) {
            return [];
        }

        $records = [];
        foreach ($recordNodes as $recordNode) {
            $record = $this->parseRecord($recordNode, $searchedTitle);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function parseRecord(SimpleXMLElement $recordNode, ?string $searchedTitle): ?CatalogueRecord
    {
        $recordNode->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $title = $this->firstValue($recordNode, 'title');
        if ($title === null) {
            return null;
        }

        $language = $this->firstValue($recordNode, 'language');
        if ($language !== null && !str_contains(mb_strtolower($language), 'fre')) {
            return null;
        }

        $publisher = $this->firstValue($recordNode, 'publisher');
        $type = $this->firstValue($recordNode, 'type');
        if (!$this->relevanceFilter->isRelevant($searchedTitle ?? $title, $title, $publisher, $type)) {
            return null;
        }

        $parsedTitle = $this->titleParser->parse($title);
        $identifiers = $this->allValues($recordNode, 'identifier');

        return new CatalogueRecord(
            workTitle: $parsedTitle->workTitle,
            headQualifier: $parsedTitle->headQualifier,
            volumeNumber: $parsedTitle->volumeNumber,
            trailingQualifier: $parsedTitle->trailingQualifier,
            publisher: $publisher,
            author: $this->authorName($this->firstValue($recordNode, 'creator')),
            isbn: $this->extractIsbn($identifiers),
            coverUrl: $this->coverUrl($identifiers),
            source: 'bnf',
        );
    }

    /** @param list<string> $identifiers */
    private function extractIsbn(array $identifiers): ?Isbn
    {
        foreach ($identifiers as $identifier) {
            $candidate = (string) preg_replace('/^(?:urn:isbn:|isbn\s*)/i', '', trim($identifier));
            $isbn = Isbn::tryFrom($candidate);
            if ($isbn !== null) {
                return $isbn;
            }
        }

        return null;
    }

    /**
     * BnF serves a record's cover from its ARK identifier — no extra request needed.
     *
     * @param list<string> $identifiers
     */
    private function coverUrl(array $identifiers): ?string
    {
        foreach ($identifiers as $identifier) {
            if (preg_match('#ark:/12148/cb[0-9a-z]+#', $identifier, $matches) === 1) {
                return sprintf('%s/couverture?appName=NE&idArk=%s&couverture=1', $this->baseUrl, $matches[0]);
            }
        }

        return null;
    }

    /** "Miura, Kentarō (1966-2021). Auteur du texte" → "Kentarō Miura". */
    private function authorName(?string $creator): ?string
    {
        if ($creator === null) {
            return null;
        }

        $withoutRole  = (string) preg_replace('/\.\s+[^.]*$/u', '', $creator);
        $withoutDates = trim((string) preg_replace('/\s*\([^)]*\)/u', '', $withoutRole));
        $parts        = array_map('trim', explode(',', $withoutDates, 2));

        $name = count($parts) === 2 && $parts[1] !== '' ? $parts[1] . ' ' . $parts[0] : $parts[0];

        return $name !== '' ? $name : null;
    }

    private function firstValue(SimpleXMLElement $recordNode, string $element): ?string
    {
        return $this->allValues($recordNode, $element)[0] ?? null;
    }

    /** @return list<string> */
    private function allValues(SimpleXMLElement $recordNode, string $element): array
    {
        /** @var array<SimpleXMLElement>|false $nodes */
        $nodes = $recordNode->xpath('.//dc:' . $element);
        if ($nodes === false) {
            return [];
        }

        $values = [];
        foreach ($nodes as $node) {
            $value = trim((string) $node);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * The value is wrapped in a CQL double-quoted phrase: only the double quote must go
     * (apostrophes are literal inside the phrase — escaping them makes BnF match nothing).
     */
    private function escape(string $value): string
    {
        return str_replace('"', '', $value);
    }
}
