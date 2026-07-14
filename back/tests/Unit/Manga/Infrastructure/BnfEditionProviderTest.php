<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\ExternalApi\BnfEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BnfEditionProviderTest extends TestCase
{
    private const string BASE_URL = 'https://catalogue.bnf.fr';

    private function makeProvider(MockHttpClient $httpClient): BnfEditionProvider
    {
        return new BnfEditionProvider(
            $httpClient,
            self::BASE_URL,
            new NullLogger(),
            new EditionLineExtractor(),
            new EditionRelevanceFilter(new PublisherNormalizer()),
        );
    }

    private function fixtureXml(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/Bnf/berserk-sru-dublincore.xml',
        );
    }

    public function testFindEditionsReturnsDtosFromXml(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertNotEmpty($editions);
        $publishers = array_column(array_map(fn ($e) => $e->toArray(), $editions), 'publisher');
        $this->assertContains('Glénat', $publishers);
    }

    public function testFindEditionsSetsFrLanguageAndCountry(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        foreach ($editions as $edition) {
            $this->assertSame('fr', $edition->language);
            $this->assertSame('FR', $edition->country);
            $this->assertSame('bnf', $edition->source);
        }
    }

    public function testFindEditionsExtractsIsbn13(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $isbns = array_filter(
            array_column(array_map(fn ($e) => $e->toArray(), $editions), 'isbnSample'),
        );

        $this->assertNotEmpty($isbns);
        foreach ($isbns as $isbn) {
            $this->assertMatchesRegularExpression('/^97[89]\d{10}$/', $isbn);
        }
    }

    public function testFindEditionsFiltersOutNonMangaRecords(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        // The fixture has 6 records but 3 are noise (video, guide, partwork).
        $this->assertCount(3, $editions);

        $publishers = array_column(array_map(fn ($e) => $e->toArray(), $editions), 'publisher');
        foreach ($publishers as $publisher) {
            $this->assertStringNotContainsStringIgnoringCase('vidéo', (string) $publisher);
            $this->assertStringNotContainsStringIgnoringCase('Third Editions', (string) $publisher);
            $this->assertStringNotContainsStringIgnoringCase('Hachette collections', (string) $publisher);
        }
    }

    public function testFindEditionsExtractsEditionLineFromRecordTitle(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $lines = array_column(array_map(fn ($e) => $e->toArray(), $editions), 'editionLine');
        $this->assertContains('Deluxe', $lines);
        $this->assertContains('Édition originale', $lines);
        // The plain numbered volume has no edition line.
        $this->assertContains(null, $lines);
    }

    public function testFindEditionsReturnsEmptyForEnglishLanguage(): void
    {
        $httpClient = new MockHttpClient([]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'en');

        $this->assertSame([], $editions);
    }

    public function testFindEditionsReturnsEmptyOnNonOkResponse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([], $editions);
    }

    public function testFindEditionsReturnsEmptyOnMalformedXml(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('<invalid xml>', ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([], $editions);
    }

    public function testFindEditionsStopsAfterSinglePageWhenAllRecordsFetched(): void
    {
        // numberOfRecords=6 in the fixture: everything fits in the first page.
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse($this->fixtureXml(), ['http_code' => 200]);
        });

        $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame(1, $requestCount);
    }

    public function testFindEditionsPaginatesUntilNumberOfRecordsIsReached(): void
    {
        // Pretend the catalogue holds 150 records: page 1 (start 1) and page 2 (start
        // 101) must be fetched, then the loop stops (201 > 150).
        $largeCatalogueXml = str_replace(
            '<srw:numberOfRecords>6</srw:numberOfRecords>',
            '<srw:numberOfRecords>150</srw:numberOfRecords>',
            $this->fixtureXml(),
        );

        $startRecords = [];
        $httpClient   = new MockHttpClient(
            function (string $method, string $url) use (&$startRecords, $largeCatalogueXml): MockResponse {
                preg_match('/startRecord=(\d+)/', $url, $matches);
                $startRecords[] = (int) ($matches[1] ?? 0);

                return new MockResponse($largeCatalogueXml, ['http_code' => 200]);
            },
        );

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([1, 101], $startRecords);
        // Both pages return the same 3 relevant records — deduplicated by ISBN.
        $this->assertCount(3, $editions);
    }

    public function testFindEditionsCapsPaginationAtThreePages(): void
    {
        $hugeCatalogueXml = str_replace(
            '<srw:numberOfRecords>6</srw:numberOfRecords>',
            '<srw:numberOfRecords>1000</srw:numberOfRecords>',
            $this->fixtureXml(),
        );

        $startRecords = [];
        $httpClient   = new MockHttpClient(
            function (string $method, string $url) use (&$startRecords, $hugeCatalogueXml): MockResponse {
                preg_match('/startRecord=(\d+)/', $url, $matches);
                $startRecords[] = (int) ($matches[1] ?? 0);

                return new MockResponse($hugeCatalogueXml, ['http_code' => 200]);
            },
        );

        $this->makeProvider($httpClient)->findEditions('Berserk', null, null);

        $this->assertSame([1, 101, 201], $startRecords);
    }

    public function testFindEditionsWithAuthorAlsoSweepsWithoutAuthorAndDeduplicates(): void
    {
        // Artbooks are often catalogued under another creator: a second query without
        // the author restriction must run, and shared records must not double up.
        $queries    = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$queries): MockResponse {
            preg_match('/query=([^&]+)/', $url, $matches);
            $queries[] = urldecode($matches[1] ?? '');

            return new MockResponse($this->fixtureXml(), ['http_code' => 200]);
        });

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', 'Kentaro Miura', null);

        $this->assertCount(2, $queries);
        $this->assertStringContainsString('bib.author all "Kentaro Miura"', $queries[0]);
        $this->assertStringNotContainsString('bib.author', $queries[1]);
        // Same fixture served twice → the 3 relevant records appear only once.
        $this->assertCount(3, $editions);
    }
}
