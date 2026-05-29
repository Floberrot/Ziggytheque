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
 * Discovers French editions from the Bibliothèque nationale de France (BnF)
 * via its SRU API, using the Dublin Core record schema.
 *
 * Thanks to the legal deposit (dépôt légal), the BnF catalogs every work
 * published in France, making it the authoritative source for French editions.
 * It is therefore only queried for {@see Country::France}; any other country
 * yields no results without an HTTP call.
 *
 * @phpstan-import-type RawVolume from EditionGrouper
 */
final readonly class BnfEditionDiscoveryClient implements EditionDiscoveryInterface
{
    private const string PREFIX_LOGGER = 'BNF_EDITIONS : ';
    private const string SRW_NAMESPACE = 'http://www.loc.gov/zing/srw/';
    private const string DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const string COVERS_BASE_URL = 'https://covers.openlibrary.org';
    private const int MAX_RECORDS = 50;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private EditionGrouper $editionGrouper,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return ExternalEditionDto[]
     */
    public function discoverEditions(string $workTitle, Country $country): array
    {
        if ($country !== Country::France) {
            return [];
        }

        $this->logger->info(self::PREFIX_LOGGER . 'discoverEditions; BEGIN.', [
            'title' => $workTitle,
        ]);

        $body = $this->fetch($workTitle);
        if ($body === null) {
            return [];
        }

        $rawVolumes = $this->parseRecords($body, $country->language());

        $this->logger->info(self::PREFIX_LOGGER . 'discoverEditions; raw volumes parsed.', [
            'count' => count($rawVolumes),
        ]);

        return $this->editionGrouper->group($rawVolumes, $country->language(), 'bnf');
    }

    private function fetch(string $workTitle): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl, [
                'query' => [
                    'version'        => '1.2',
                    'operation'      => 'searchRetrieve',
                    'query'          => sprintf('bib.title all "%s"', $this->escapeCqlValue($workTitle)),
                    'recordSchema'   => 'dublincore',
                    'maximumRecords' => self::MAX_RECORDS,
                ],
            ]);

            return $response->getContent();
        } catch (Throwable $exception) {
            $this->logger->warning(self::PREFIX_LOGGER . 'fetch failed.', [
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
            $this->logger->warning(self::PREFIX_LOGGER . 'XML parse failed.');
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
        $recordNode->registerXPathNamespace('dc', self::DUBLIN_CORE_NAMESPACE);

        $title = $this->firstValue($recordNode, 'dc:title');
        if ($title === null) {
            return null;
        }

        // The BnF is overwhelmingly French, but co-editions in other languages
        // are catalogued too; keep only records matching the requested language.
        $recordLanguage = $this->firstValue($recordNode, 'dc:language');
        if ($recordLanguage !== null && !$this->matchesLanguage($recordLanguage, $language)) {
            return null;
        }

        $isbn = $this->extractIsbn($recordNode);

        return [
            'publisher'    => $this->firstValue($recordNode, 'dc:publisher'),
            'year'         => $this->extractYear($recordNode),
            'volumeNumber' => $this->extractVolumeNumber($title),
            'title'        => $title,
            'coverUrl'     => $isbn !== null ? $this->buildCoverUrl($isbn) : null,
            'isbn'         => $isbn,
        ];
    }

    private function firstValue(SimpleXMLElement $recordNode, string $xpath): ?string
    {
        $nodes = $recordNode->xpath($xpath) ?: [];
        if ($nodes === []) {
            return null;
        }

        $value = trim((string) $nodes[0]);

        return $value === '' ? null : $value;
    }

    private function matchesLanguage(string $recordLanguage, string $language): bool
    {
        return strtolower(trim($recordLanguage)) === $this->toBnfLanguageCode($language);
    }

    private function toBnfLanguageCode(string $language): string
    {
        return match ($language) {
            'fr' => 'fre',
            'en' => 'eng',
            'ja' => 'jpn',
            'de' => 'ger',
            'es' => 'spa',
            default => $language,
        };
    }

    private function extractYear(SimpleXMLElement $recordNode): ?int
    {
        $date = $this->firstValue($recordNode, 'dc:date');
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
        if (preg_match('/\b(?:tome|vol(?:ume)?|t)[\s.]*(\d+)/i', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        // Fallback for "Dragon Ball. 3" / "Dragon Ball, 3" style numbering.
        if (preg_match('/[.,]\s*(\d{1,3})\b/', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractIsbn(SimpleXMLElement $recordNode): ?string
    {
        foreach ($recordNode->xpath('dc:identifier') ?: [] as $identifierNode) {
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

    private function escapeCqlValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
