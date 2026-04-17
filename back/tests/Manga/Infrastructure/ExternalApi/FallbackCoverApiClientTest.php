<?php

declare(strict_types=1);

namespace App\Tests\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\Exception\ExternalApiUnavailableException;
use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Infrastructure\ExternalApi\FallbackCoverApiClient;
use PHPUnit\Framework\TestCase;

class FallbackCoverApiClientTest extends TestCase
{
    public function testPrimarySuccessReturnsPrimaryResults(): void
    {
        $dto = new ExternalMangaDto(
            externalId: '/books/OL123M',
            title: 'Test Manga',
            edition: null,
            author: null,
            summary: null,
            coverUrl: 'https://covers.openlibrary.org/b/id/9876543-M.jpg',
            genre: null,
            language: 'fr',
            source: 'openlibrary',
        );

        $primary = $this->createMock(ExternalApiClientInterface::class);
        $primary->method('searchByTitle')->willReturn([$dto]);

        $secondary = $this->createMock(ExternalApiClientInterface::class);

        $client = new FallbackCoverApiClient($primary, $secondary);
        $result = $client->search('test manga');

        $this->assertSame('openlibrary', $result['source']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('Test Manga', $result['results'][0]->title);
    }

    public function testPrimaryEmptyCallsSecondary(): void
    {
        $dto = new ExternalMangaDto(
            externalId: 'google-456',
            title: 'Test Manga',
            edition: null,
            author: null,
            summary: null,
            coverUrl: 'https://example.com/cover2.jpg',
            genre: null,
            language: 'fr',
            source: 'google',
        );

        $primary = $this->createMock(ExternalApiClientInterface::class);
        $primary->method('searchByTitle')->willReturn([]);

        $secondary = $this->createMock(ExternalApiClientInterface::class);
        $secondary->method('searchByTitle')->willReturn([$dto]);

        $client = new FallbackCoverApiClient($primary, $secondary);
        $result = $client->search('test manga');

        $this->assertSame('google', $result['source']);
        $this->assertCount(1, $result['results']);
    }

    public function testPrimaryExceptionCallsSecondary(): void
    {
        $dto = new ExternalMangaDto(
            externalId: 'google-789',
            title: 'Test Manga',
            edition: null,
            author: null,
            summary: null,
            coverUrl: 'https://example.com/cover3.jpg',
            genre: null,
            language: 'fr',
            source: 'google',
        );

        $primary = $this->createMock(ExternalApiClientInterface::class);
        $primary->method('searchByTitle')->willThrowException(new \Exception('Network error'));

        $secondary = $this->createMock(ExternalApiClientInterface::class);
        $secondary->method('searchByTitle')->willReturn([$dto]);

        $client = new FallbackCoverApiClient($primary, $secondary);
        $result = $client->search('test manga');

        $this->assertSame('google', $result['source']);
        $this->assertCount(1, $result['results']);
    }

    public function testBothFailThrowsUnavailable(): void
    {
        $primary = $this->createMock(ExternalApiClientInterface::class);
        $primary->method('searchByTitle')->willThrowException(new \Exception('Primary failed'));

        $secondary = $this->createMock(ExternalApiClientInterface::class);
        $secondary->method('searchByTitle')->willThrowException(new \Exception('Secondary failed'));

        $client = new FallbackCoverApiClient($primary, $secondary);

        $this->expectException(ExternalApiUnavailableException::class);
        $client->search('test manga');
    }

    public function testPrimaryEmptySecondaryFailsThrows(): void
    {
        $primary = $this->createMock(ExternalApiClientInterface::class);
        $primary->method('searchByTitle')->willReturn([]);

        $secondary = $this->createMock(ExternalApiClientInterface::class);
        $secondary->method('searchByTitle')->willThrowException(new \Exception('Secondary failed'));

        $client = new FallbackCoverApiClient($primary, $secondary);

        $this->expectException(ExternalApiUnavailableException::class);
        $client->search('test manga');
    }
}
