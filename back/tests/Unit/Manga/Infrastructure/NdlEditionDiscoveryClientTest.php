<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\NdlEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NdlEditionDiscoveryClientTest extends TestCase
{
    private const string BASE_URL = 'https://ndlsearch.ndl.go.jp/api/sru';

    private function makeClient(MockHttpClient $httpClient): NdlEditionDiscoveryClient
    {
        return new NdlEditionDiscoveryClient(
            $httpClient,
            self::BASE_URL,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    /**
     * SRU dcndl_simple response with two Japanese Shueisha volumes (dates in
     * dcterms:date, ISBN in dcndl:ISBN) and one English VIZ Media volume.
     */
    private function sruResponse(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
          <srw:version>1.2</srw:version>
          <srw:numberOfRecords>3</srw:numberOfRecords>
          <srw:records>
            <srw:record>
              <srw:recordData>
                <srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-v1.1"
                           xmlns:dc="http://purl.org/dc/elements/1.1/"
                           xmlns:dcterms="http://purl.org/dc/terms/"
                           xmlns:dcndl="http://ndl.go.jp/dcndl/terms/">
                  <dc:title>ドラゴンボール 1</dc:title>
                  <dc:creator>鳥山, 明</dc:creator>
                  <dc:publisher>集英社</dc:publisher>
                  <dcterms:date>1985</dcterms:date>
                  <dc:language>jpn</dc:language>
                  <dcndl:ISBN>978-4-08-851831-5</dcndl:ISBN>
                </srw_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-v1.1"
                           xmlns:dc="http://purl.org/dc/elements/1.1/"
                           xmlns:dcterms="http://purl.org/dc/terms/"
                           xmlns:dcndl="http://ndl.go.jp/dcndl/terms/">
                  <dc:title>ドラゴンボール 2</dc:title>
                  <dc:publisher>集英社</dc:publisher>
                  <dcterms:date>1986</dcterms:date>
                  <dc:language>jpn</dc:language>
                  <dcndl:ISBN>978-4-08-851832-2</dcndl:ISBN>
                </srw_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <srw_dc:dc xmlns:srw_dc="info:srw/schema/1/dc-v1.1"
                           xmlns:dc="http://purl.org/dc/elements/1.1/"
                           xmlns:dcterms="http://purl.org/dc/terms/">
                  <dc:title>Dragon Ball, Vol. 1</dc:title>
                  <dc:publisher>VIZ Media</dc:publisher>
                  <dcterms:date>2003</dcterms:date>
                  <dc:language>eng</dc:language>
                </srw_dc:dc>
              </srw:recordData>
            </srw:record>
          </srw:records>
        </srw:searchRetrieveResponse>
        XML;
    }

    public function testDiscoverEditionsGroupsJapaneseVolumesByPublisher(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('ドラゴンボール', Country::Japan);

        // The English VIZ Media record is filtered out, leaving one Shueisha
        // edition spanning the two Japanese volumes.
        $this->assertCount(1, $editions);
        $this->assertSame('集英社', $editions[0]->publisher);
        $this->assertSame('ndl', $editions[0]->source);
        $this->assertSame('ja', $editions[0]->language);
        $this->assertSame(1985, $editions[0]->year);
        $this->assertSame(2, $editions[0]->volumeCount);
    }

    public function testParsesIsbnFromDcndlElement(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('ドラゴンボール', Country::Japan);

        $this->assertSame('9784088518315', $editions[0]->sampleIsbn);
        $this->assertStringContainsString('9784088518315', (string) $editions[0]->coverUrl);
    }

    public function testReturnsEmptyForNonJapanWithoutHttpCall(): void
    {
        // No mock responses: any HTTP call would raise, proving the country
        // guard short-circuits before reaching the network.
        $httpClient = new MockHttpClient([]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('ドラゴンボール', Country::France));
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testReturnsEmptyOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('ドラゴンボール', Country::Japan));
    }
}
