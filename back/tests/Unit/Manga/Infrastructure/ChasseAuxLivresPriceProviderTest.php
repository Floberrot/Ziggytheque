<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Infrastructure\ExternalApi\ChasseAuxLivresPriceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ChasseAuxLivresPriceProviderTest extends TestCase
{
    private const string BASE_URL = 'https://www.chasse-aux-livres.fr';
    private const string ISBN = '9782723425483';

    private function makeProvider(
        MockHttpClient $httpClient,
        string $baseUrl = self::BASE_URL,
    ): ChasseAuxLivresPriceProvider {
        return new ChasseAuxLivresPriceProvider($httpClient, $baseUrl, new NullLogger());
    }

    private function fixtureHtml(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/ChasseAuxLivres/prix-berserk.html',
        );
    }

    public function testParsesMerchantOffersFromComparisonPage(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml(), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertCount(3, $offers);

        $merchants = array_map(static fn ($offer) => $offer->merchant, $offers);
        $this->assertSame(['Amazon', 'Rakuten', 'momox'], $merchants);

        $amounts = array_map(static fn ($offer) => $offer->amount, $offers);
        $this->assertSame([7.20, 6.50, 4.99], $amounts);

        foreach ($offers as $offer) {
            $this->assertSame(PriceKindEnum::MerchantLive, $offer->kind);
            $this->assertSame('EUR', $offer->currency);
            $this->assertSame('chasse_aux_livres', $offer->source);
        }
    }

    public function testAbsolutizesRelativeAndProtocolRelativeUrls(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml(), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame(self::BASE_URL . '/redirection/amazon/' . self::ISBN, $offers[0]->url);
        $this->assertSame('https://rakuten.example/offre/123', $offers[1]->url);
        $this->assertSame('https://momox.example/article/' . self::ISBN, $offers[2]->url);
    }

    public function testQueriesThePricePageForTheIsbn(): void
    {
        $requestedUrl = null;
        $httpClient   = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse($this->fixtureHtml(), ['http_code' => 200]);
        });

        $this->makeProvider($httpClient)->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame(self::BASE_URL . '/prix/' . self::ISBN, $requestedUrl);
    }

    public function testReturnsEmptyForNonFrenchMarketplaceWithoutRequest(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse($this->fixtureHtml(), ['http_code' => 200]);
        });

        $offers = $this->makeProvider($httpClient)->findOffers(Isbn::fromString(self::ISBN), Marketplace::Us);

        $this->assertSame([], $offers);
        $this->assertSame(0, $requestCount);
    }

    public function testEmptyBaseUrlDisablesTheProvider(): void
    {
        $requestCount = 0;
        $httpClient   = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse($this->fixtureHtml(), ['http_code' => 200]);
        });

        $offers = $this->makeProvider($httpClient, '')->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame([], $offers);
        $this->assertSame(0, $requestCount);
    }

    public function testReturnsEmptyOnNonOkResponse(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse('', ['http_code' => 403]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame([], $offers);
    }

    public function testReturnsEmptyOnHtmlWithoutOffers(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse('<html><body><p>Aucun résultat</p></body></html>', ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame([], $offers);
    }
}
