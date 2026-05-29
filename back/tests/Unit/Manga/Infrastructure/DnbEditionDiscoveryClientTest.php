<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\DnbEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DnbEditionDiscoveryClientTest extends TestCase
{
    private const string BASE_URL = 'https://services.dnb.de/sru/dnb';

    private function makeClient(MockHttpClient $httpClient): DnbEditionDiscoveryClient
    {
        return new DnbEditionDiscoveryClient(
            $httpClient,
            self::BASE_URL,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    /**
     * SRU oai_dc response with two German Carlsen volumes (the first carrying a
     * city suffix on its publisher) and one French Glénat volume.
     */
    private function sruResponse(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <srw:searchRetrieveResponse xmlns:srw="http://www.loc.gov/zing/srw/">
          <srw:version>1.1</srw:version>
          <srw:numberOfRecords>3</srw:numberOfRecords>
          <srw:records>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Band 1</dc:title>
                  <dc:creator>Toriyama, Akira</dc:creator>
                  <dc:publisher>Carlsen (Hamburg)</dc:publisher>
                  <dc:date>2001</dc:date>
                  <dc:language>ger</dc:language>
                  <dc:identifier>ISBN 978-3-551-74201-0</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Band 2</dc:title>
                  <dc:publisher>Carlsen</dc:publisher>
                  <dc:date>2001</dc:date>
                  <dc:language>ger</dc:language>
                  <dc:identifier>ISBN 9783551742027</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Tome 1</dc:title>
                  <dc:publisher>Glénat</dc:publisher>
                  <dc:date>1993</dc:date>
                  <dc:language>fre</dc:language>
                  <dc:identifier>ISBN 978-2-344-02081-4</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
          </srw:records>
        </srw:searchRetrieveResponse>
        XML;
    }

    public function testDiscoverEditionsGroupsGermanVolumesByPublisher(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Dragon Ball', Country::Germany);

        // The French Glénat record is filtered out, and the two German records
        // merge into one edition once the "(Hamburg)" city suffix is stripped.
        $this->assertCount(1, $editions);
        $this->assertSame('Carlsen', $editions[0]->publisher);
        $this->assertSame('dnb', $editions[0]->source);
        $this->assertSame('de', $editions[0]->language);
        $this->assertSame(2001, $editions[0]->year);
        $this->assertSame(2, $editions[0]->volumeCount);
        $this->assertSame('9783551742010', $editions[0]->sampleIsbn);
    }

    public function testReturnsEmptyForNonGermanyWithoutHttpCall(): void
    {
        // No mock responses: any HTTP call would raise, proving the country
        // guard short-circuits before reaching the network.
        $httpClient = new MockHttpClient([]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::France));
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testReturnsEmptyOnHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Service Unavailable', ['http_code' => 503]),
        ]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::Germany));
    }

    public function testReturnsEmptyOnMalformedXml(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('this is not xml')]);

        $client = $this->makeClient($httpClient);

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::Germany));
    }
}
