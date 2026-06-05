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
}
