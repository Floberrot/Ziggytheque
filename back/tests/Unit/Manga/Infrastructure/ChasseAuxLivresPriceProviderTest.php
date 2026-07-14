<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Infrastructure\ExternalApi\ChasseAuxLivresPriceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        ?LoggerInterface $logger = null,
    ): ChasseAuxLivresPriceProvider {
        return new ChasseAuxLivresPriceProvider($httpClient, $baseUrl, $logger ?? new NullLogger());
    }

    private function fixtureHtml(string $name = 'prix-berserk'): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/ChasseAuxLivres/' . $name . '.html',
        );
    }

    // ── Strategy: table rows (merchant from img alt) ─────────────────────────

    public function testParsesMerchantOffersFromComparisonTable(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml(), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertCount(5, $offers);

        $merchants = array_map(static fn ($offer) => $offer->merchant, $offers);
        $this->assertSame(['Amazon', 'Fnac.com', 'eBay France', 'Rakuten', 'momox'], $merchants);

        $amounts = array_map(static fn ($offer) => $offer->amount, $offers);
        $this->assertSame([7.20, 7.90, 5.40, 6.50, 4.99], $amounts);

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
        $this->assertSame('https://rakuten.example/offre/123', $offers[3]->url);
        $this->assertSame('https://momox.example/article/' . self::ISBN, $offers[4]->url);
    }

    // ── Strategy: JSON-LD (schema.org Product / Offer) ───────────────────────

    public function testParsesOffersFromJsonLdWhenNoDomRowsExist(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml('prix-jsonld'), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        // The zero-price offer and the seller-less offer are both skipped.
        $this->assertCount(2, $offers);

        $this->assertSame('Amazon', $offers[0]->merchant);
        $this->assertSame(7.20, $offers[0]->amount);
        $this->assertSame(self::BASE_URL . '/redirection/amazon/' . self::ISBN, $offers[0]->url);

        $this->assertSame('Fnac', $offers[1]->merchant);
        $this->assertSame(6.99, $offers[1]->amount);
        $this->assertSame('https://www.fnac.com/livre/' . self::ISBN, $offers[1]->url);

        foreach ($offers as $offer) {
            $this->assertSame(PriceKindEnum::MerchantLive, $offer->kind);
            $this->assertSame('EUR', $offer->currency);
        }
    }

    // ── Strategy: offer-classed blocks + data-merchant/seller/vendor ─────────

    public function testParsesOffersFromBlocksWithMerchantDataAttributes(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml('prix-blocks'), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $merchants = array_map(static fn ($offer) => $offer->merchant, $offers);
        $this->assertSame(['Amazon', 'Fnac', 'momox'], $merchants);

        $amounts = array_map(static fn ($offer) => $offer->amount, $offers);
        $this->assertSame([7.20, 7.90, 4.99], $amounts);

        $this->assertSame(self::BASE_URL . '/redirection/fnac/' . self::ISBN, $offers[1]->url);
    }

    // ── Strategy: tolerant text fallback (known merchant near "xx,xx €") ─────

    public function testFallsBackToKnownMerchantsInPlainTextOnUnexpectedLayout(): void
    {
        $provider = $this->makeProvider(new MockHttpClient([
            new MockResponse($this->fixtureHtml('prix-fallback-texte'), ['http_code' => 200]),
        ]));

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertCount(3, $offers);

        $merchants = array_map(static fn ($offer) => mb_strtolower($offer->merchant), $offers);
        $this->assertSame(['amazon', 'fnac', 'ebay'], $merchants);

        $amounts = array_map(static fn ($offer) => $offer->amount, $offers);
        $this->assertSame([7.20, 7.90, 5.40], $amounts);

        foreach ($offers as $offer) {
            $this->assertNull($offer->url);
            $this->assertSame(PriceKindEnum::MerchantLive, $offer->kind);
        }
    }

    // ── Diagnostics ──────────────────────────────────────────────────────────

    public function testLogsWarningWhenPageIsOkButNoOfferParsed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('0 OFFER PARSED'),
                $this->callback(static fn (array $context) => ($context['isbn'] ?? null) === self::ISBN),
            );

        $provider = $this->makeProvider(
            new MockHttpClient([
                new MockResponse('<html><body><p>Aucun résultat</p></body></html>', ['http_code' => 200]),
            ]),
            logger: $logger,
        );

        $offers = $provider->findOffers(Isbn::fromString(self::ISBN), Marketplace::Fr);

        $this->assertSame([], $offers);
    }

    // ── Request shaping / guards ─────────────────────────────────────────────

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
