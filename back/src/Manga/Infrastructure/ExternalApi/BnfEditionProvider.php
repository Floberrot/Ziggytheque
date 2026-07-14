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
use SimpleXMLElement;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class BnfEditionProvider implements EditionProviderInterface
{
    private const string LOG_PREFIX = 'BNF EDITIONS : ';

    /** SRU pagination: up to 3 pages of 100 records (BnF caps maximumRecords at 100). */
    private const int PAGE_SIZE = 100;
    private const int MAX_PAGES = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private LoggerInterface $logger,
        private EditionLineExtractor $lineExtractor,
        private EditionRelevanceFilter $relevanceFilter,
    ) {
    }

    public function findEditions(string $workTitle, ?string $author, ?string $language): array
    {
        if ($language !== null && $language !== 'fr') {
            return [];
        }

        $this->logger->info(self::LOG_PREFIX . 'findEditions; BEGIN.', ['title' => $workTitle]);

        try {
            return $this->doFindEditions($workTitle, $author);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_PREFIX . 'findEditions; ERROR.', [
                'title' => $workTitle,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return list<ExternalEditionDto> */
    private function doFindEditions(string $workTitle, ?string $author): array
    {
        $cqlQueries = [$this->buildCql($workTitle, $author)];

        // Artbooks, coffrets and companion volumes are often catalogued under another
        // creator (illustrator, studio) — sweep once more without the author restriction
        // so they are not silently excluded. Results are deduplicated by ISBN below.
        if ($author !== null && $author !== '') {
            $cqlQueries[] = $this->buildCql($workTitle, null);
        }

        /** @var array<string, true> $seenKeys */
        $seenKeys = [];
        $editions = [];
        foreach ($cqlQueries as $cqlQuery) {
            foreach ($this->fetchAllPages($cqlQuery, $workTitle) as $edition) {
                $dedupeKey = $edition->isbnSample
                    ?? mb_strtolower($edition->editionLabel . '|' . $edition->format->value);
                if (isset($seenKeys[$dedupeKey])) {
                    continue;
                }
                $seenKeys[$dedupeKey] = true;
                $editions[]           = $edition;
            }
        }

        return $editions;
    }

    private function buildCql(string $workTitle, ?string $author): string
    {
        $cqlQuery = sprintf('bib.title all "%s"', $this->escapeCql($workTitle));
        if ($author !== null && $author !== '') {
            $cqlQuery .= sprintf(' and bib.author all "%s"', $this->escapeCql($author));
        }

        return $cqlQuery;
    }

    /** @return list<ExternalEditionDto> */
    private function fetchAllPages(string $cqlQuery, string $workTitle): array
    {
        $editions    = [];
        $startRecord = 1;

        for ($pageIndex = 0; $pageIndex < self::MAX_PAGES; $pageIndex++) {
            $page = $this->fetchPage($cqlQuery, $workTitle, $startRecord);
            if ($page === null) {
                break;
            }

            foreach ($page['editions'] as $edition) {
                $editions[] = $edition;
            }

            $startRecord += self::PAGE_SIZE;
            if ($page['recordCount'] === 0 || $startRecord > $page['numberOfRecords']) {
                break;
            }
        }

        return $editions;
    }

    /** @return array{editions: list<ExternalEditionDto>, numberOfRecords: int, recordCount: int}|null */
    private function fetchPage(string $cqlQuery, string $workTitle, int $startRecord): ?array
    {
        $sruParams = http_build_query([
            'version'        => '1.2',
            'operation'      => 'searchRetrieve',
            'query'          => $cqlQuery,
            'recordSchema'   => 'dublincore',
            'maximumRecords' => (string) self::PAGE_SIZE,
            'startRecord'    => (string) $startRecord,
        ]);
        $url = sprintf('%s/api/SRU?%s', $this->baseUrl, $sruParams);

        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            $this->logger->info(self::LOG_PREFIX . 'findEditions; NOT 200.', [
                'status' => $response->getStatusCode(),
            ]);

            return null;
        }

        return $this->parseResponse($response->getContent(), $workTitle);
    }

    /** @return array{editions: list<ExternalEditionDto>, numberOfRecords: int, recordCount: int}|null */
    private function parseResponse(string $xmlContent, string $workTitle): ?array
    {
        if ($xmlContent === '') {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($xmlContent);

        $xml->registerXPathNamespace('srw', 'http://www.loc.gov/zing/srw/');
        $xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        /** @var array<SimpleXMLElement>|false $totalNodes */
        $totalNodes      = $xml->xpath('//srw:numberOfRecords');
        $numberOfRecords = $totalNodes !== false && $totalNodes !== []
            ? (int) ($totalNodes[0] ?? 0)
            : 0;

        /** @var array<SimpleXMLElement>|false $records */
        $records = $xml->xpath('//srw:record/srw:recordData');
        if ($records === false || $records === []) {
            return ['editions' => [], 'numberOfRecords' => $numberOfRecords, 'recordCount' => 0];
        }

        $editions = [];
        foreach ($records as $record) {
            $edition = $this->parseRecord($record, $workTitle);
            if ($edition !== null) {
                $editions[] = $edition;
            }
        }

        return [
            'editions'        => $editions,
            'numberOfRecords' => $numberOfRecords,
            'recordCount'     => count($records),
        ];
    }

    private function parseRecord(SimpleXMLElement $record, string $workTitle): ?ExternalEditionDto
    {
        $record->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $record->registerXPathNamespace('oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');

        /** @var array<SimpleXMLElement>|false $titleNodes */
        $titleNodes = $record->xpath('.//dc:title');
        /** @var array<SimpleXMLElement>|false $publisherNodes */
        $publisherNodes = $record->xpath('.//dc:publisher');
        /** @var array<SimpleXMLElement>|false $identifiers */
        $identifiers = $record->xpath('.//dc:identifier');
        /** @var array<SimpleXMLElement>|false $formatNodes */
        $formatNodes = $record->xpath('.//dc:format');
        /** @var array<SimpleXMLElement>|false $typeNodes */
        $typeNodes = $record->xpath('.//dc:type');

        if ($titleNodes === false || $titleNodes === []) {
            return null;
        }

        $recordTitle = (string) ($titleNodes[0] ?? '');
        $publisher   = $publisherNodes !== false && $publisherNodes !== [] ? (string) ($publisherNodes[0] ?? '') : null;
        $publisher   = ($publisher !== null && $publisher !== '') ? $publisher : null;
        $dcFormat    = $formatNodes !== false && $formatNodes !== [] ? (string) ($formatNodes[0] ?? '') : '';
        $dcType      = $typeNodes !== false && $typeNodes !== [] ? (string) ($typeNodes[0] ?? '') : '';

        // Drop video releases, figurine partworks and derivative noise — keep every
        // print edition whose title matches the work, artbooks included.
        if (!$this->relevanceFilter->isRelevant($workTitle, $recordTitle, $publisher, $dcType)) {
            return null;
        }

        $isbnSample = null;
        if ($identifiers !== false) {
            foreach ($identifiers as $identifier) {
                $extracted = $this->extractIsbn((string) $identifier);
                if ($extracted !== null) {
                    $isbnSample = $extracted;
                    break;
                }
            }
        }

        // The record's real title (e.g. "Dragon Ball : perfect edition. 1") carries the
        // edition line the search term never could — recover it here.
        $editionLine = $this->lineExtractor->extract($recordTitle);
        $format      = EditionFormatEnum::fromRawLabel($editionLine ?? ($dcFormat !== '' ? $dcFormat : $recordTitle));
        $label       = $editionLine !== null
            ? sprintf('%s — %s', $publisher ?? $workTitle, $editionLine)
            : ($publisher ?? $workTitle);

        return new ExternalEditionDto(
            workTitle:    $workTitle,
            editionLabel: $label,
            publisher:    $publisher,
            language:     'fr',
            country:      'FR',
            format:       $format,
            volumeCount:  null,
            isbnSample:   $isbnSample,
            coverUrl:     null,
            source:       'bnf',
            editionLine:  $editionLine,
        );
    }

    /**
     * The title is wrapped in a CQL double-quoted phrase, so only the double quote must
     * go — apostrophes (frequent in French titles like "L'Attaque des titans") are
     * literal inside the phrase and must NOT be backslash-escaped (BnF then matches nothing).
     */
    private function escapeCql(string $value): string
    {
        return str_replace('"', '', $value);
    }

    private function extractIsbn(string $raw): ?string
    {
        // Strip common prefixes like "ISBN " or "urn:isbn:"
        $cleaned = preg_replace('/^(urn:isbn:|ISBN\s*)/i', '', trim($raw)) ?? $raw;

        $isbn = Isbn::tryFrom($cleaned);

        return $isbn?->value;
    }
}
