<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\BnfCoversApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BnfCoversApiClientTest extends TestCase
{
    private const string BASE_URL = 'https://catalogue.bnf.fr';

    private function makeClient(MockHttpClient $httpClient): BnfCoversApiClient
    {
        return new BnfCoversApiClient($httpClient, self::BASE_URL, new NullLogger());
    }

    private function makeIsbn(): Isbn
    {
        return Isbn::fromString('9782123456780');
    }

    private function sruWithArk(string $ark = 'ark:/12148/cb453653801'): string
    {
        return '<?xml version="1.0"?><srw:searchRetrieveResponse xmlns:srw="info:srw/schema/1/srw">'
            . '<srw:records><srw:record><mxc:controlfield tag="003">http://catalogue.bnf.fr/' . $ark . '</mxc:controlfield>'
            . '</srw:record></srw:records></srw:searchRetrieveResponse>';
    }

    private function imageResponse(int $size = 50000): MockResponse
    {
        return new MockResponse(str_repeat('x', $size), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'image/jpeg'],
        ]);
    }

    public function testFindByIsbnReturnsDtoWhenRecordAndCoverExist(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->sruWithArk()),
            $this->imageResponse(),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('bnf', $result->source);
        $this->assertStringContainsString('couverture', $result->coverUrl);
        $this->assertStringContainsString('ark:/12148/cb453653801', $result->coverUrl);
        $this->assertNull($result->spineUrl);
    }

    public function testFindByIsbnReturnsNullWhenNoRecord(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('<?xml version="1.0"?><srw:searchRetrieveResponse xmlns:srw="info:srw/schema/1/srw"><srw:numberOfRecords>0</srw:numberOfRecords></srw:searchRetrieveResponse>'),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenCoverIsNotAnImage(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->sruWithArk()),
            new MockResponse('<html>not found</html>', [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'text/html'],
            ]),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullWhenCoverTooSmall(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->sruWithArk()),
            $this->imageResponse(100),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testFindByIsbnReturnsNullOnSruHttpError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('error', ['http_code' => 500]),
        ]);

        $result = $this->makeClient($httpClient)->findByIsbn($this->makeIsbn());

        $this->assertNull($result);
    }

    public function testSruQueryUsesIsbnIndex(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;
            return new MockResponse('<empty/>');
        });

        $isbn = $this->makeIsbn();
        $this->makeClient($httpClient)->findByIsbn($isbn);

        $this->assertCount(1, $requestedUrls);
        $decoded = urldecode($requestedUrls[0]);
        $this->assertStringContainsString('bib.isbn', $decoded);
        $this->assertStringContainsString($isbn->value, $decoded);
        $this->assertStringContainsString('/api/SRU', $requestedUrls[0]);
    }

    public function testFindByContextAlwaysReturnsNull(): void
    {
        $client = $this->makeClient(new MockHttpClient([]));

        $this->assertNull($client->findByContext('One Piece', null, 1));
    }
}
