<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Service\EditionLineExtractor;
use App\Manga\Domain\Service\EditionRelevanceFilter;
use App\Manga\Domain\Service\PublisherNormalizer;
use App\Manga\Infrastructure\ExternalApi\NdlEditionProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NdlEditionProviderTest extends TestCase
{
    private const string BASE_URL = 'https://ndlsearch.ndl.go.jp/api/sru';

    private function makeProvider(MockHttpClient $httpClient): NdlEditionProvider
    {
        return new NdlEditionProvider(
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
            dirname(__DIR__, 3) . '/Fixtures/Ndl/shingeki-sru-dcndl.xml',
        );
    }

    public function testReturnsJapaneseEditionsFromKanjiRecords(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja');

        $this->assertNotEmpty($editions);
        foreach ($editions as $edition) {
            $this->assertSame('ja', $edition->language);
            $this->assertSame('JP', $edition->country);
            $this->assertSame('ndl', $edition->source);
        }
    }

    public function testUnknownPublisherIsFilteredOut(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja');

        // 4 records in the fixture, the unknown-publisher one is dropped → 3 remain.
        $this->assertCount(3, $editions);
    }

    public function testExtractsKanjiEditionLines(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja');

        $lines = array_column(array_map(fn ($edition) => $edition->toArray(), $editions), 'editionLine');
        $this->assertContains('Édition couleur', $lines);  // カラー版
        $this->assertContains('Perfect Edition', $lines);  // 完全版
        $this->assertContains(null, $lines);               // plain numbered volume
    }

    public function testExtractsIsbnAndMapsPublisherToRomaji(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixtureXml(), ['http_code' => 200]),
        ]);

        $editions = $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja');

        $this->assertSame('9784063842760', $editions[0]->isbnSample);
        // Raw publisher stays as catalogued; the grouper relabels via PublisherNormalizer.
        $this->assertSame('講談社', $editions[0]->publisher);
    }

    public function testQueriesNdlWithNativeTitleCql(): void
    {
        $requestedUrl = null;
        $httpClient   = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse($this->fixtureXml(), ['http_code' => 200]);
        });

        $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja');

        $this->assertNotNull($requestedUrl);
        $this->assertStringStartsWith(self::BASE_URL . '?', $requestedUrl);
        $this->assertStringContainsString(rawurlencode('title="進撃の巨人"'), str_replace('+', '%20', $requestedUrl));
    }

    public function testReturnsEmptyForNonJapaneseLanguageWithoutRequest(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse($this->fixtureXml(), ['http_code' => 200]);
        });

        $editions = $this->makeProvider($httpClient)->findEditions('Berserk', null, 'fr');

        $this->assertSame([], $editions);
        $this->assertSame(0, $requestCount);
    }

    public function testReturnsEmptyOnNonOkResponse(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('', ['http_code' => 503])]);

        $this->assertSame([], $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja'));
    }

    public function testReturnsEmptyOnMalformedXml(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('<broken', ['http_code' => 200])]);

        $this->assertSame([], $this->makeProvider($httpClient)->findEditions('進撃の巨人', null, 'ja'));
    }
}
