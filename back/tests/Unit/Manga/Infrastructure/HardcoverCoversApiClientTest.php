<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\MangaVolumeCoverDto;
use App\Manga\Infrastructure\ExternalApi\HardcoverCoversApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HardcoverCoversApiClientTest extends TestCase
{
    private const string BASE_URL = 'https://api.hardcover.app/v1/graphql';

    private function makeClient(MockHttpClient $httpClient, string $token): HardcoverCoversApiClient
    {
        return new HardcoverCoversApiClient($httpClient, self::BASE_URL, $token, new NullLogger());
    }

    /** @param list<array<string, mixed>> $editions */
    private function graphqlResponse(array $editions): MockResponse
    {
        return new MockResponse(
            json_encode(['data' => ['editions' => $editions]]),
            ['response_headers' => ['Content-Type' => 'application/json']],
        );
    }

    public function testReturnsNullAndMakesNoRequestWithoutToken(): void
    {
        $requested = false;
        $httpClient = new MockHttpClient(function () use (&$requested): MockResponse {
            $requested = true;
            return new MockResponse('{}');
        });

        $result = $this->makeClient($httpClient, '')->findByIsbn(Isbn::fromString('9782811645632'));

        $this->assertNull($result);
        $this->assertFalse($requested, 'No HTTP request should be made without a token.');
    }

    public function testReturnsCoverWhenEditionHasImage(): void
    {
        $httpClient = new MockHttpClient([
            $this->graphqlResponse([
                ['image' => ['url' => 'https://assets.hardcover.app/edition/1/content.jpeg']],
            ]),
        ]);

        $result = $this->makeClient($httpClient, 'token')->findByIsbn(Isbn::fromString('9782811645632'));

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('hardcover', $result->source);
        $this->assertSame('https://assets.hardcover.app/edition/1/content.jpeg', $result->coverUrl);
        $this->assertNull($result->spineUrl);
    }

    public function testSkipsEditionsWithoutImageAndReturnsFirstWithCover(): void
    {
        $httpClient = new MockHttpClient([
            $this->graphqlResponse([
                ['image' => null],
                ['image' => ['url' => 'https://assets.hardcover.app/edition/2/content.jpeg']],
            ]),
        ]);

        $result = $this->makeClient($httpClient, 'token')->findByIsbn(Isbn::fromString('9782811645632'));

        $this->assertInstanceOf(MangaVolumeCoverDto::class, $result);
        $this->assertSame('https://assets.hardcover.app/edition/2/content.jpeg', $result->coverUrl);
    }

    public function testReturnsNullWhenNoEditionMatches(): void
    {
        $httpClient = new MockHttpClient([$this->graphqlResponse([])]);

        $result = $this->makeClient($httpClient, 'token')->findByIsbn(Isbn::fromString('9782811645632'));

        $this->assertNull($result);
    }

    public function testReturnsNullOnHttpError(): void
    {
        $httpClient = new MockHttpClient([new MockResponse('error', ['http_code' => 500])]);

        $result = $this->makeClient($httpClient, 'token')->findByIsbn(Isbn::fromString('9782811645632'));

        $this->assertNull($result);
    }

    public function testSendsBearerTokenAndIsbnQuery(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];
            return $this->graphqlResponse([
                ['image' => ['url' => 'https://assets.hardcover.app/edition/3/content.jpeg']],
            ]);
        });

        $this->makeClient($httpClient, 'secret-token')->findByIsbn(Isbn::fromString('9782723488525'));

        $this->assertSame('POST', $captured['method']);
        $this->assertSame(self::BASE_URL, $captured['url']);

        $body = (string) $captured['options']['body'];
        $this->assertStringContainsString('isbn_13', $body);
        $this->assertStringContainsString('9782723488525', $body);

        $headers = implode("\n", $captured['options']['headers'] ?? []);
        $this->assertStringContainsString('Authorization: Bearer secret-token', $headers);
    }

    public function testStripsRedundantBearerPrefixFromToken(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;
            return $this->graphqlResponse([
                ['image' => ['url' => 'https://assets.hardcover.app/edition/9/content.jpeg']],
            ]);
        });

        // Hardcover's settings page shows the value already prefixed with "Bearer ".
        $this->makeClient($httpClient, 'Bearer abc123')->findByIsbn(Isbn::fromString('9782811645632'));

        $headers = implode("\n", $captured['headers'] ?? []);
        $this->assertStringContainsString('Authorization: Bearer abc123', $headers);
        $this->assertStringNotContainsString('Bearer Bearer', $headers);
    }

    public function testFindByContextReturnsNull(): void
    {
        $result = $this->makeClient(new MockHttpClient([]), 'token')->findByContext('One Piece', null, 1);

        $this->assertNull($result);
    }
}
