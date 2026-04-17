<?php

declare(strict_types=1);

namespace App\Tests\Manga\Infrastructure\ExternalApi;

use App\Manga\Infrastructure\ExternalApi\AmazonBooksMangaApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AmazonBooksMangaApiClientTest extends TestCase
{
    private AmazonBooksMangaApiClient $client;
    private MockHttpClient $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        $this->client = new AmazonBooksMangaApiClient(
            $this->mockHttpClient,
            'test-access-key',
            'test-secret-key',
            'test-partner-tag',
            'www.amazon.fr',
        );
    }

    public function testSearchReturnsExternalMangaDtosWithAmazonSource(): void
    {
        $responseData = [
            'products' => [
                [
                    'ASIN' => 'B001',
                    'ItemInfo' => [
                        'Title' => ['DisplayValue' => 'One Piece Vol 1'],
                        'ByLineInfo' => [
                            'Contributors' => [['Name' => 'Eiichiro Oda']],
                        ],
                        'ManufactureInfo' => ['Manufacturer' => 'Jump'],
                        'Features' => ['DisplayValues' => ['Great manga']],
                    ],
                    'Images' => [
                        'Primary' => ['Large' => ['URL' => 'https://example.com/cover.jpg']],
                    ],
                ],
            ],
        ];

        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode($responseData))
        );

        $results = $this->client->searchByTitle('One Piece');

        $this->assertCount(1, $results);
        $this->assertSame('B001', $results[0]->externalId);
        $this->assertSame('One Piece Vol 1', $results[0]->title);
        $this->assertSame('amazon', $results[0]->source);
        $this->assertSame('https://example.com/cover.jpg', $results[0]->coverUrl);
    }

    public function testSearchReturnsEmptyArrayOnNoResults(): void
    {
        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode(['products' => []]))
        );

        $results = $this->client->searchByTitle('Nonexistent Manga');

        $this->assertSame([], $results);
    }

    public function testSearchThrowsOnHttpError(): void
    {
        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse('', ['http_code' => 500])
        );

        $this->expectException(\Throwable::class);
        $this->client->searchByTitle('Test');
    }
}
