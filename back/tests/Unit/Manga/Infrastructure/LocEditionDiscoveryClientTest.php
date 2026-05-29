<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Country;
use App\Manga\Domain\Service\EditionGrouper;
use App\Manga\Infrastructure\ExternalApi\LocEditionDiscoveryClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LocEditionDiscoveryClientTest extends TestCase
{
    private const string BASE_URL = 'https://www.loc.gov/sru';

    private function makeClient(MockHttpClient $httpClient): LocEditionDiscoveryClient
    {
        return new LocEditionDiscoveryClient(
            $httpClient,
            self::BASE_URL,
            new EditionGrouper(),
            new NullLogger(),
        );
    }

    /**
     * SRU oai_dc response with two English VIZ Media volumes and one French
     * Glénat volume; the French record must be filtered out.
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
                  <dc:publisher>VIZ Media</dc:publisher>
                  <dc:date>2003</dc:date>
                  <dc:language>eng</dc:language>
                  <dc:identifier>ISBN 978-1-56931-920-1</dc:identifier>
                </oai_dc:dc>
              </srw:recordData>
            </srw:record>
            <srw:record>
              <srw:recordData>
                <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                           xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <dc:title>Dragon Ball. Vol. 2</dc:title>
                  <dc:publisher>VIZ Media</dc:publisher>
                  <dc:date>2003</dc:date>
                  <dc:language>eng</dc:language>
                  <dc:identifier>ISBN 9781569319208</dc:identifier>
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

    public function testDiscoverEditionsGroupsEnglishVolumesByPublisher(): void
    {
        $httpClient = new MockHttpClient([new MockResponse($this->sruResponse())]);

        $client = $this->makeClient($httpClient);
        $editions = $client->discoverEditions('Dragon Ball', Country::UnitedStates);

        // The French Glénat record is filtered out, leaving one VIZ Media edition.
        $this->assertCount(1, $editions);
        $this->assertSame('VIZ Media', $editions[0]->publisher);
        $this->assertSame('loc', $editions[0]->source);
        $this->assertSame('en', $editions[0]->language);
        $this->assertSame(2003, $editions[0]->year);
        $this->assertSame(2, $editions[0]->volumeCount);
    }

    public function testReturnsEmptyForNonUsWithoutHttpCall(): void
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

        $this->assertSame([], $client->discoverEditions('Dragon Ball', Country::UnitedStates));
    }
}
