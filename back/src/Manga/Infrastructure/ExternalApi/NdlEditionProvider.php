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

/**
 * NDL Search SRU — the National Diet Library, Japan's legal-deposit catalogue, via its
 * official keyless SRU API (same protocol as BnF/DNB). Authoritative for Japanese
 * editions (Shueisha, Kodansha, Hakusensha…), which no other wired source covers.
 *
 * Queried with the work's native title (進撃の巨人), resolved upstream by
 * {@see AniListWorkTitleResolver}; record titles carry the edition markers in kanji
 * (完全版, カラー版…) which {@see EditionLineExtractor} maps to canonical lines.
 */
final readonly class NdlEditionProvider implements EditionProviderInterface
{
    private const string LOG_PREFIX = 'NDL EDITIONS : ';

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
        if ($language !== null && $language !== 'ja') {
            return [];
        }

        if ($this->baseUrl === '' || $workTitle === '') {
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
        $cqlQuery = sprintf('title="%s"', str_replace('"', '', $workTitle));
        if ($author !== null && $author !== '') {
            $cqlQuery .= sprintf(' AND creator="%s"', str_replace('"', '', $author));
        }

        $sruParams = http_build_query([
            'operation'      => 'searchRetrieve',
            'version'        => '1.2',
            'recordSchema'   => 'dcndl_simple',
            'recordPacking'  => 'xml',
            'query'          => $cqlQuery,
            'maximumRecords' => '100',
        ]);
        $url = sprintf('%s?%s', $this->baseUrl, $sruParams);

        $response = $this->httpClient->request('GET', $url);
        if ($response->getStatusCode() !== 200) {
            $this->logger->info(self::LOG_PREFIX . 'findEditions; NOT 200.', [
                'status' => $response->getStatusCode(),
            ]);

            return [];
        }

        return $this->parseResponse($response->getContent(), $workTitle);
    }

    /** @return list<ExternalEditionDto> */
    private function parseResponse(string $xmlContent, string $workTitle): array
    {
        if ($xmlContent === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($xmlContent);

        $xml->registerXPathNamespace('srw', 'http://www.loc.gov/zing/srw/');

        /** @var array<SimpleXMLElement>|false $records */
        $records = $xml->xpath('//srw:record/srw:recordData');
        if ($records === false || $records === []) {
            return [];
        }

        $editions = [];
        foreach ($records as $record) {
            $edition = $this->parseRecord($record, $workTitle);
            if ($edition !== null) {
                $editions[] = $edition;
            }
        }

        return $editions;
    }

    private function parseRecord(SimpleXMLElement $record, string $workTitle): ?ExternalEditionDto
    {
        $record->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        /** @var array<SimpleXMLElement>|false $titleNodes */
        $titleNodes = $record->xpath('.//dc:title');
        /** @var array<SimpleXMLElement>|false $publisherNodes */
        $publisherNodes = $record->xpath('.//dc:publisher');
        /** @var array<SimpleXMLElement>|false $identifiers */
        $identifiers = $record->xpath('.//dc:identifier');
        /** @var array<SimpleXMLElement>|false $typeNodes */
        $typeNodes = $record->xpath('.//dc:type');

        if ($titleNodes === false || $titleNodes === []) {
            return null;
        }

        $recordTitle = (string) ($titleNodes[0] ?? '');
        $publisher   = $publisherNodes !== false && $publisherNodes !== [] ? (string) ($publisherNodes[0] ?? '') : null;
        $publisher   = ($publisher !== null && $publisher !== '') ? $publisher : null;
        $dcType      = $typeNodes !== false && $typeNodes !== [] ? (string) ($typeNodes[0] ?? '') : '';

        if (!$this->relevanceFilter->isRelevant($recordTitle, $publisher, $dcType)) {
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

        $editionLine = $this->lineExtractor->extract($recordTitle);
        $format      = EditionFormatEnum::fromRawLabel($editionLine ?? $recordTitle);
        $label       = $editionLine !== null
            ? sprintf('%s — %s', $publisher ?? $workTitle, $editionLine)
            : ($publisher ?? $workTitle);

        return new ExternalEditionDto(
            workTitle:    $workTitle,
            editionLabel: $label,
            publisher:    $publisher,
            language:     'ja',
            country:      'JP',
            format:       $format,
            volumeCount:  null,
            isbnSample:   $isbnSample,
            coverUrl:     null,
            source:       'ndl',
            editionLine:  $editionLine,
        );
    }

    private function extractIsbn(string $raw): ?string
    {
        $cleaned = preg_replace('/^(urn:isbn:|ISBN\s*)/i', '', trim($raw)) ?? $raw;

        return Isbn::tryFrom($cleaned)?->value;
    }
}
