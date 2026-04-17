<?php

declare(strict_types=1);

namespace App\Tests\Manga\Application\SearchVolumeExternal;

use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalHandler;
use App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalQuery;
use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

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
            source: 'google',
        );

        $mockClient = $this->createMock(ExternalApiClientInterface::class);
        $mockClient->method('searchByTitle')->willReturn([$dto]);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $handler = new SearchVolumeExternalHandler($mockClient, $mockLogger);
        $result = $handler(new SearchVolumeExternalQuery('test query'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertSame('google', $result['source']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('test-123', $result['results'][0]['externalId']);
        $this->assertSame('https://example.com/cover.jpg', $result['results'][0]['coverUrl']);
    }

    public function testHandlerReturnsEmptyResults(): void
    {
        $mockClient = $this->createMock(ExternalApiClientInterface::class);
        $mockClient->method('searchByTitle')->willReturn([]);
        $mockLogger = $this->createMock(LoggerInterface::class);

        $handler = new SearchVolumeExternalHandler($mockClient, $mockLogger);
        $result = $handler(new SearchVolumeExternalQuery('nonexistent'));

        $this->assertSame('google', $result['source']);
        $this->assertSame([], $result['results']);
    }

    public function testHandlerPropagatesDomainException(): void
    {
        $mockClient = $this->createMock(ExternalApiClientInterface::class);
        $mockClient->method('searchByTitle')->willThrowException(
            new ExternalApiUnavailableException()
        );
        $mockLogger = $this->createMock(LoggerInterface::class);

        $handler = new SearchVolumeExternalHandler($mockClient, $mockLogger);

        $this->expectException(ExternalApiUnavailableException::class);
        $handler(new SearchVolumeExternalQuery('test'));
    }
}
