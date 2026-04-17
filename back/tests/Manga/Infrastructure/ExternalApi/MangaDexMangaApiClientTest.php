<?php

declare(strict_types=1);

namespace App\Tests\Manga\Infrastructure\ExternalApi;

use App\Manga\Infrastructure\ExternalApi\MangaDexMangaApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MangaDexMangaApiClientTest extends TestCase
{
    private MangaDexMangaApiClient $client;
    private MockHttpClient $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->client = new MangaDexMangaApiClient($this->mockHttpClient, $logger);
    }

    public function testSearchReturnsExternalMangaDtosWithMangaDexSource(): void
    {
        $responseData = [
            'data' => [
                [
                    'id' => 'manga-123',
                    'attributes' => [
                        'title' => ['en' => 'One Piece'],
                        'description' => ['en' => 'Great manga'],
                        'tags' => [
                            [
                                'id' => 'tag-1',
                                'attributes' => [
                                    'name' => ['en' => 'Action'],
                                ],
                            ],
                        ],
                    ],
                    'relationships' => [
                        [
                            'id' => 'author-1',
                            'type' => 'author',
                            'attributes' => [
                                'name' => 'Eiichiro Oda',
                            ],
                        ],
                        [
                            'id' => 'cover-1',
                            'type' => 'cover_art',
                            'attributes' => [
                                'fileName' => 'cover123',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode($responseData))
        );

        $results = $this->client->searchByTitle('One Piece');

        $this->assertCount(1, $results);
        $this->assertSame('manga-123', $results[0]->externalId);
        $this->assertSame('One Piece', $results[0]->title);
        $this->assertSame('mangadex', $results[0]->source);
        $this->assertStringContainsString('uploads.mangadex.org', $results[0]->coverUrl ?? '');
        $this->assertSame('Eiichiro Oda', $results[0]->author);
    }

    public function testSearchReturnsEmptyArrayOnNoResults(): void
    {
        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode(['data' => []]))
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
