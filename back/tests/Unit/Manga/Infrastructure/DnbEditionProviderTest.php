<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\ExternalApi\DnbEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DnbEditionProviderTest extends TestCase
{
    private const string BASE_URL = 'https://services.dnb.de/sru/dnb';

    private function makeProvider(MockHttpClient $httpClient): DnbEditionProvider
    {
        return new DnbEditionProvider(
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
            dirname(__DIR__, 3) . '/Fixtures/Dnb/dragonball-sru-oai-dc.xml',
        );
    }

    public function testReturnsGermanEditions(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        $this->assertNotEmpty($editions);
        foreach ($editions as $edition) {
            $this->assertSame('de', $edition->language);
            $this->assertSame('DE', $edition->country);
            $this->assertSame('dnb', $edition->source);
        }
    }

    public function testFiltersOutArtbookAndKeepsRealEditions(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        // 3 records, the artbook is dropped → 2 real editions remain.
        $this->assertCount(2, $editions);
        foreach ($editions as $edition) {
            $this->assertStringNotContainsStringIgnoringCase('artbook', $edition->editionLabel);
        }
    }

    public function testExtractsColorEditionLine(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        $lines = array_column(array_map(fn ($e) => $e->toArray(), $editions), 'editionLine');
        $this->assertContains('Édition couleur', $lines);
        $this->assertContains(null, $lines);
    }

    public function testExtractsIsbn13(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        $isbns = array_filter(array_column(array_map(fn ($e) => $e->toArray(), $editions), 'isbnSample'));
        $this->assertNotEmpty($isbns);
        foreach ($isbns as $isbn) {
            $this->assertMatchesRegularExpression('/^97[89]\d{10}$/', $isbn);
        }
    }

    public function testReturnsEmptyForNonGermanLanguage(): void
    {
        $editions = $this->makeProvider(new MockHttpClient([]))->findEditions('Dragon Ball', null, 'fr');

        $this->assertSame([], $editions);
    }

    public function testReturnsEmptyOnNonOkResponse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        $this->assertSame([], $editions);
    }

    public function testReturnsEmptyOnMalformedXml(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('<invalid xml>', ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('Dragon Ball', null, null);

        $this->assertSame([], $editions);
    }
}
