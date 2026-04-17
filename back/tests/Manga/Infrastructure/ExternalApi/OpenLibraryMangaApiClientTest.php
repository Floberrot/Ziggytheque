<?php

declare(strict_types=1);

namespace App\Tests\Manga\Infrastructure\ExternalApi;

use App\Manga\Infrastructure\ExternalApi\OpenLibraryMangaApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenLibraryMangaApiClientTest extends TestCase
{
    private OpenLibraryMangaApiClient $client;
    private MockHttpClient $mockHttpClient;

    protected function setUp(): void
    {
        $this->mockHttpClient = new MockHttpClient();
        $this->client = new OpenLibraryMangaApiClient($this->mockHttpClient);
    }

    public function testSearchReturnsExternalMangaDtosWithOpenLibrarySource(): void
    {
        $responseData = [
            'docs' => [
                [
                    'key' => '/books/OL1234M',
                    'title' => 'One Piece Vol 1',
                    'author_name' => ['Eiichiro Oda'],
                    'publisher' => ['Jump'],
                    'cover_id' => 9876543,
                    'language' => ['fr'],
                    'subject' => ['manga', 'action'],
                ],
            ],
        ];

        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode($responseData))
        );

        $results = $this->client->searchByTitle('One Piece');

        $this->assertCount(1, $results);
        $this->assertSame('/books/OL1234M', $results[0]->externalId);
        $this->assertSame('One Piece Vol 1', $results[0]->title);
        $this->assertSame('openlibrary', $results[0]->source);
        $this->assertStringContainsString('covers.openlibrary.org', $results[0]->coverUrl ?? '');
        $this->assertSame('Eiichiro Oda', $results[0]->author);
    }

    public function testSearchReturnsEmptyArrayOnNoResults(): void
    {
        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode(['docs' => []]))
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

    public function testSearchWithoutCoverId(): void
    {
        $responseData = [
            'docs' => [
                [
                    'key' => '/books/OL5678M',
                    'title' => 'Test Manga',
                    'author_name' => ['Test Author'],
                    'publisher' => ['Test Publisher'],
                    'language' => ['fr'],
                ],
            ],
        ];

        $this->mockHttpClient->setResponseFactory(
            fn () => new MockResponse(json_encode($responseData))
        );

        $results = $this->client->searchByTitle('Test Manga');

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->coverUrl);
        $this->assertSame('openlibrary', $results[0]->source);
    }
}
