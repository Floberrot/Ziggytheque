<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\BnfEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BnfEditionDiscoveryClientTest extends TestCase
{
    private const string BASE_URL = 'https://catalogue.bnf.fr/api/SRU';

    private function makeClient(MockHttpClient $httpClient): BnfEditionDiscoveryClient
    {
        return new BnfEditionDiscoveryClient(
            $httpClient,
            self::BASE_URL,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    /**
     * SRU response with two French Glénat volumes (one carrying an ARK URI
     * alongside its ISBN) and one English VIZ Media volume.
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
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Tome 1</dc:title>
                  <dc:creator>Toriyama, Akira</dc:creator>
                  <dc:publisher>Glénat</dc:publisher>
                  <dc:date>1993</dc:date>
                  <dc:language>fre</dc:language>
                  <dc:identifier>http://catalogue.bnf.fr/ark:/12148/cb39476789p</dc:identifier>
                  <dc:identifier>ISBN 978-2-344-02081-4</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Tome 2</dc:title>
                  <dc:publisher>Glénat</dc:publisher>
                  <dc:date>DL 1994</dc:date>
                  <dc:language>fre</dc:language>
                  <dc:identifier>ISBN 9782344020822</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball, Vol. 1</dc:title>
                  <dc:publisher>VIZ Media</dc:publisher>
                  <dc:date>2003</dc:date>
                  <dc:language>eng</dc:language>
                  <dc:identifier>ISBN 978-1-56931-920-1</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
          </srw:records>
        </srw:searchRetrieveResponse>
        XML;
    }

    public function testDiscoverEditionsGroupsFrenchVolumesByPublisher(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Dragon Ball', Country::France);

        // The English VIZ Media record is filtered out, leaving one Glénat edition.
        $this->assertCount(1, $editions);
        $this->assertSame('Glénat', $editions[0]->publisher);
        $this->assertSame('bnf', $editions[0]->source);
        $this->assertSame(1993, $editions[0]->year);
        $this->assertSame(2, $editions[0]->volumeCount);
    }

    public function testDiscoverEditionsPicksIsbnOverArkIdentifier(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Dragon Ball', Country::France);

        $this->assertSame('9782344020814', $editions[0]->sampleIsbn);
        $this->assertStringContainsString('covers.openlibrary.org', (string) $editions[0]->coverUrl);
        $this->assertStringContainsString('9782344020814', (string) $editions[0]->coverUrl);
    }

    public function testReturnsEmptyForNonFranceWithoutHttpCall(): void
    {
        // No mock responses: any HTTP call would raise, proving the country guard
        // short-circuits before reaching the network.
        $httpClient = new MockHttpClient([]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::Japan));
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testReturnsEmptyOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::France));
    }

    public function testReturnsEmptyOnMalformedXml(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('this is not xml')]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::France));
    }

    public function testReturnsEmptyWhenNoRecordsMatchLanguage(): void
    {
        $englishOnly = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
          <srw:records>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball, Vol. 1</dc:title>
                  <dc:publisher>VIZ Media</dc:publisher>
                  <dc:language>eng</dc:language>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
          </srw:records>
        </srw:searchRetrieveResponse>
        XML;

        $httpClient = new MockHttpClient([new MockResponse($englishOnly)]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::France));
    }
}
