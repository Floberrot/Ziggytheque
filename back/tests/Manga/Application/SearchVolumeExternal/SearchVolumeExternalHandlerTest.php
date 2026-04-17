<?php

declare(strict_types=1);

namespace App\Tests\Manga\Application\SearchVolumeExternal;

use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalHandler;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Infrastructure\ExternalApi\FallbackCoverApiClient;
use PHPUnit\Framework\TestCase;

class SearchVolumeExternalHandlerTest extends TestCase
{
    public function testHandlerReturnsSourceAndResults(): void
    {
        $dto = new ExternalMangaDto(
            externalId: 'test-123',
            title: 'Test Manga',
            edition: 'Pika',
            author: 'Test Author',
            summary: 'A test summary',
            coverUrl: 'https://example.com/cover.jpg',
            genre: 'action',
            language: 'fr',
            source: 'amazon',
        );

        $mockClient = $this->createMock(FallbackCoverApiClient::class);
        $mockClient->method('search')->willReturn([
            'source' => 'amazon',
            'results' => [$dto],
        ]);

        $handler = new SearchVolumeExternalHandler($mockClient);
        $result = $handler(new SearchVolumeExternalQuery('test query'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertSame('amazon', $result['source']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('test-123', $result['results'][0]['externalId']);
        $this->assertSame('https://example.com/cover.jpg', $result['results'][0]['coverUrl']);
    }

    public function testHandlerReturnsNoneOnEmptyResults(): void
    {
        $mockClient = $this->createMock(FallbackCoverApiClient::class);
        $mockClient->method('search')->willReturn([
            'source' => 'none',
            'results' => [],
        ]);

        $handler = new SearchVolumeExternalHandler($mockClient);
        $result = $handler(new SearchVolumeExternalQuery('nonexistent'));

        $this->assertSame('none', $result['source']);
        $this->assertSame([], $result['results']);
    }

    public function testHandlerPropagatesDomainException(): void
    {
        $mockClient = $this->createMock(FallbackCoverApiClient::class);
        $mockClient->method('search')->willThrowException(
            new ExternalApiUnavailableException()
        );

        $handler = new SearchVolumeExternalHandler($mockClient);

        $this->expectException(ExternalApiUnavailableException::class);
        $handler(new SearchVolumeExternalQuery('test'));
    }
}
