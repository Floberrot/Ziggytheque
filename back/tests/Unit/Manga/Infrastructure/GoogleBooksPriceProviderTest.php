<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Infrastructure\ExternalApi\GoogleBooksPriceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleBooksPriceProviderTest extends TestCase
{
    private const string API_KEY = 'test-key-abc';

    private function makeProvider(MockHttpClient $httpClient, string $apiKey = self::API_KEY): GoogleBooksPriceProvider
    {
        return new GoogleBooksPriceProvider($httpClient, $apiKey, new NullLogger());
    }

    private function fixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/GoogleBooks/saleinfo-fr.json',
        );
    }

    public function testFindOffersReturnsPublisherReferenceWhenForSale(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->fixture(), ['http_code' => 200]),
        ]);

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient)->findOffers($isbn, Marketplace::Fr);

        $this->assertCount(1, $offers);
        $this->assertSame(PriceKindEnum::PublisherReference, $offers[0]->kind);
        $this->assertSame('Google Play', $offers[0]->merchant);
        $this->assertSame('EUR', $offers[0]->currency);
        $this->assertGreaterThan(0.0, $offers[0]->amount);
        $this->assertNotNull($offers[0]->url);
    }

    public function testFindOffersReturnsEmptyWhenNotForSale(): void
    {
        $notForSale = json_encode([
            'totalItems' => 1,
            'items' => [[
                'id' => 'xyz',
                'volumeInfo' => ['title' => 'Test'],
                'saleInfo' => ['saleability' => 'NOT_FOR_SALE'],
            ]],
        ]);

        $httpClient = new MockHttpClient([
            new MockResponse((string) $notForSale, ['http_code' => 200]),
        ]);

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient)->findOffers($isbn, Marketplace::Fr);

        $this->assertSame([], $offers);
    }

    public function testFindOffersReturnsEmptyWhenNoItems(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"totalItems": 0, "items": []}', ['http_code' => 200]),
        ]);

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient)->findOffers($isbn, Marketplace::Fr);

        $this->assertSame([], $offers);
    }

    public function testFindOffersReturnsEmptyWithEmptyApiKey(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse('{}', ['http_code' => 200]);
        });

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient, '')->findOffers($isbn, Marketplace::Fr);

        $this->assertSame([], $offers);
        $this->assertSame(0, $requestCount);
    }
}
