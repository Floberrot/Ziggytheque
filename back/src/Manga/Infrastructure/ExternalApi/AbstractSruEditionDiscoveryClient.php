<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Country;
use App\Manga\Domain\EditionDiscoveryInterface;
use App\Manga\Domain\ExternalEditionDto;
use App\Manga\Domain\Service\EditionGrouper;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Shared logic for discovering editions from a national library's SRU API
 * using Dublin Core (or Dublin-Core-compatible) record schemas.
 *
 * Thanks to the legal deposit (dépôt légal / Pflichtexemplar / 法定納本),
 * national libraries catalogue every work published in their country, making
 * them the authoritative source for that market's editions. Each concrete
 * client therefore serves exactly one {@see Country} and short-circuits for
 * any other without an HTTP call.
 *
 * @phpstan-import-type RawVolume from EditionGrouper
 */
abstract readonly class AbstractSruEditionDiscoveryClient implements EditionDiscoveryInterface
{
    private const string SRW_NAMESPACE = 'http://www.loc.gov/zing/srw/';
    private const string DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const string DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';
    private const string DCNDL_NAMESPACE = 'http://ndl.go.jp/dcndl/terms/';
    private const string COVERS_BASE_URL = 'https://covers.openlibrary.org';

    /**
     * ISO 639-1 request language → record language codes accepted as a match.
     * National libraries label languages with MARC/ISO 639-2 codes (fre, ger,
     * jpn…); we accept both the 639-2/B and 639-2/T variants plus the bare
     * 639-1 code so co-edition filtering is tolerant across catalogues.
     *
     * @var array<string, list<string>>
     */
    private const array LANGUAGE_CODES = [
        'fr' => ['fre', 'fra', 'fr'],
        'en' => ['eng', 'en'],
        'ja' => ['jpn', 'ja'],
        'de' => ['ger', 'deu', 'de'],
        'es' => ['spa', 'es'],
        'it' => ['ita', 'it'],
    ];

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected string $baseUrl,
        protected EditionGrouper $editionGrouper,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * The single country whose editions this catalogue is authoritative for.
     */
    abstract protected function country(): Country;

    /**
     * Short identifier tagged on every edition produced (e.g. 'bnf', 'dnb').
     */
    abstract protected function source(): string;

    abstract protected function sruVersion(): string;

    abstract protected function recordSchema(): string;

    /**
     * Builds the catalogue-specific CQL query for a title search.
     */
    abstract protected function buildQuery(string $workTitle): string;

    /**
     * Maximum number of records to request per call.
     * Each catalogue enforces its own server-side cap; override accordingly.
     */
    abstract protected function maximumRecords(): int;

    /**
     * @return ExternalEditionDto[]
     */
    final public function discoverEditions(string $workTitle, Country $country): array
    {
        if ($country !== $this->country()) {
            return [];
        }

        $body = $this->fetch($workTitle);
        if ($body === null) {
            return [];
        }

        $rawVolumes = $this->parseRecords($body, $country->language());

        $this->logger->info($this->logPrefix() . 'raw volumes parsed.', [
            'title' => $workTitle,
            'count' => count($rawVolumes),
        ]);

        return $this->editionGrouper->group($rawVolumes, $country->language(), $this->source());
    }

    private function logPrefix(): string
    {
        return strtoupper($this->source()) . '_EDITIONS : ';
    }

    private function fetch(string $workTitle): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => [
                    'version'        => $this->sruVersion(),
                    'operation'      => 'searchRetrieve',
                    'query'          => $this->buildQuery($workTitle),
                    'recordSchema'   => $this->recordSchema(),
                    'maximumRecords' => $this->maximumRecords(),
                ],
            ]);

            return $response->getContent();
        } catch (Throwable $exception) {
            $this->logger->warning($this->logPrefix() . 'fetch failed.', [
                'message' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<int, RawVolume>
     */
    private function parseRecords(string $body, string $language): array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if ($document === false) {
            $this->logger->warning($this->logPrefix() . 'XML parse failed.');
            return [];
        }

        $document->registerXPathNamespace('srw', self::SRW_NAMESPACE);
        $recordNodes = $document->xpath('//srw:recordData/*') ?: [];

        $rawVolumes = [];

        foreach ($recordNodes as $recordNode) {
            $rawVolume = $this->normalizeRecord($recordNode, $language);
            if ($rawVolume !== null) {
                $rawVolumes[] = $rawVolume;
            }
        }

        return $rawVolumes;
    }

    /**
     * @return RawVolume|null
     */
    private function normalizeRecord(SimpleXMLElement $recordNode, string $language): ?array
    {
        $recordNode->registerXPathNamespace('dc', self::DC_NAMESPACE);
        $recordNode->registerXPathNamespace('dcterms', self::DCTERMS_NAMESPACE);
        $recordNode->registerXPathNamespace('dcndl', self::DCNDL_NAMESPACE);

        $title = $this->firstValue($recordNode, ['.//dc:title', './/dcterms:title']);
        if ($title === null) {
            return null;
        }

        // Catalogues are dominated by their own country's language, but
        // co-editions in other languages are listed too; keep only records
        // whose declared language matches the requested one.
        $recordLanguage = $this->firstValue($recordNode, ['.//dc:language', './/dcterms:language']);
        if ($recordLanguage !== null && !$this->matchesLanguage($recordLanguage, $language)) {
            return null;
        }

        $isbn = $this->extractIsbn($recordNode);

        return [
            'publisher'    => $this->normalizePublisher(
                $this->firstValue($recordNode, ['.//dc:publisher', './/dcterms:publisher']),
            ),
            'year'         => $this->extractYear($recordNode),
            'volumeNumber' => $this->extractVolumeNumber($title),
            'title'        => $title,
            'coverUrl'     => $isbn !== null ? $this->buildCoverUrl($isbn) : null,
            'isbn'         => $isbn,
        ];
    }

    /**
     * Returns the first non-empty value found across the candidate xpaths.
     *
     * @param list<string> $xpaths
     */
    private function firstValue(SimpleXMLElement $recordNode, array $xpaths): ?string
    {
        foreach ($xpaths as $xpath) {
            $nodes = $recordNode->xpath($xpath) ?: [];
            if ($nodes === []) {
                continue;
            }

            $value = trim((string) $nodes[0]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Publisher entries frequently append the city in parentheses, e.g.
     * "Glénat (Grenoble)" or "Carlsen (Hamburg)". Strip that suffix so grouping
     * and deduplication work across differently-formatted records.
     */
    private function normalizePublisher(?string $publisher): ?string
    {
        if ($publisher === null) {
            return null;
        }

        $normalized = trim((string) preg_replace('/\s*\([^)]+\)\s*$/', '', $publisher));

        return $normalized === '' ? null : $normalized;
    }

    private function matchesLanguage(string $recordLanguage, string $language): bool
    {
        $acceptable = self::LANGUAGE_CODES[$language] ?? [$language];

        return in_array(strtolower(trim($recordLanguage)), $acceptable, true);
    }

    private function extractYear(SimpleXMLElement $recordNode): ?int
    {
        $date = $this->firstValue($recordNode, ['.//dc:date', './/dcterms:date', './/dcterms:issued']);
        if ($date === null) {
            return null;
        }

        // Dates come in many shapes: "1993", "DL 1994", "cop. 2013", "2010-2015".
        if (preg_match('/(\d{4})/', $date, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractVolumeNumber(string $title): ?int
    {
        // Latin numbering: "Tome 3", "Tomo 3", "Band 3", "Vol. 3", "T03".
        if (preg_match('/\b(?:tome|tomo|band|vol(?:ume)?|t)[\s.]*(\d+)/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        // Japanese numbering: "第3巻" or a trailing "3巻".
        if (preg_match('/(\d+)\s*巻/u', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        // Fallback for "Dragon Ball. 3" / "Dragon Ball, 3" / "Dragon Ball 3".
        if (preg_match('/[.,\s](\d{1,3})\s*$/u', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractIsbn(SimpleXMLElement $recordNode): ?string
    {
        $identifierNodes = array_merge(
            $recordNode->xpath('.//dc:identifier') ?: [],
            $recordNode->xpath('.//dcndl:ISBN') ?: [],
        );

        foreach ($identifierNodes as $identifierNode) {
            $candidate = $this->normalizeIsbnCandidate((string) $identifierNode);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Identifiers can be ARK URIs, "ISBN 978-2-344-02081-4", or a bare ISBN.
     * Only return a value when it genuinely looks like an ISBN so ARK URIs
     * (which also contain long digit runs) are never mistaken for one.
     */
    private function normalizeIsbnCandidate(string $rawIdentifier): ?string
    {
        if (preg_match('/\b(97[89](?:[\s-]?\d){10})\b/', $rawIdentifier, $matches) === 1) {
            return (string) preg_replace('/\D/', '', $matches[1]);
        }

        if (stripos($rawIdentifier, 'ISBN') !== false) {
            $digits = (string) preg_replace('/[^0-9X]/i', '', strtoupper($rawIdentifier));
            if (strlen($digits) === 13 || strlen($digits) === 10) {
                return $digits;
            }
        }

        return null;
    }

    private function buildCoverUrl(string $isbn): string
    {
        return sprintf('%s/b/isbn/%s-L.jpg', self::COVERS_BASE_URL, $isbn);
    }

    protected function escapeCqlValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
