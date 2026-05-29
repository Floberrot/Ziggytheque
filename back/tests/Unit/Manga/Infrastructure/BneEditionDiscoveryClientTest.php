<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\BneEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BneEditionDiscoveryClientTest extends TestCase
{
    private const string BASE_URL = 'https://www.bne.es/SRUBib';

    private function makeClient(MockHttpClient $httpClient): BneEditionDiscoveryClient
    {
        return new BneEditionDiscoveryClient(
            $httpClient,
            self::BASE_URL,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    /**
     * SRU oai_dc response with two Spanish Planeta volumes and one English VIZ
     * volume; the English record must be filtered out.
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
                  <dc:title>Dragon Ball. Vol. 1</dc:title>
                  <dc:creator>Toriyama, Akira</dc:creator>
                  <dc:publisher>Planeta (Barcelona)</dc:publisher>
                  <dc:date>2002</dc:date>
                  <dc:language>spa</dc:language>
                  <dc:identifier>ISBN 978-84-9787-001-0</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Vol. 2</dc:title>
                  <dc:publisher>Planeta</dc:publisher>
                  <dc:date>2002</dc:date>
                  <dc:language>spa</dc:language>
                  <dc:identifier>ISBN 9788497870027</dc:identifier>
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
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
          </srw:records>
        </srw:searchRetrieveResponse>
        XML;
    }

    public function testDiscoverEditionsGroupsSpanishVolumesByPublisher(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Dragon Ball', Country::Spain);

        // The English VIZ record is filtered out, and the city suffix is stripped
        // from the first Planeta record, so both volumes merge into one edition.
        $this->assertCount(1, $editions);
        $this->assertSame('Planeta', $editions[0]->publisher);
        $this->assertSame('bne', $editions[0]->source);
        $this->assertSame('es', $editions[0]->language);
        $this->assertSame(2002, $editions[0]->year);
        $this->assertSame(2, $editions[0]->volumeCount);
    }

    public function testReturnsEmptyForNonSpainWithoutHttpCall(): void
    {
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

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::Spain));
    }
}
