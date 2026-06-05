<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceKindEnum;
use App\Manga\Infrastructure\ExternalApi\Ebay\EbayOAuthTokenProvider;
use App\Manga\Infrastructure\ExternalApi\EbayBrowsePriceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EbayBrowsePriceProviderTest extends TestCase
{
    private const string BASE_URL   = 'https://api.ebay.com';
    private const string OAUTH_URL  = 'https://api.ebay.com/identity/v1/oauth2/token';
    private const string CLIENT_ID  = 'test-client-id';
    private const string CLIENT_SECRET = 'test-secret';

    private function oauthFixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/Ebay/oauth-token.json',
        );
    }

    private function browseFixture(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/Ebay/browse-search.json',
        );
    }

    private function makeProvider(
        MockHttpClient $httpClient,
        string $clientId = self::CLIENT_ID,
    ): EbayBrowsePriceProvider {
        $cache         = new ArrayAdapter();
        $tokenProvider = new EbayOAuthTokenProvider(
            $httpClient,
            self::OAUTH_URL,
            $clientId,
            self::CLIENT_SECRET,
            $cache,
            new NullLogger(),
        );

        return new EbayBrowsePriceProvider($httpClient, $tokenProvider, self::BASE_URL, '', new NullLogger());
    }

    public function testFindOffersReturnsMerchantLiveOffers(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->oauthFixture(), ['http_code' => 200]),
            new MockResponse($this->browseFixture(), ['http_code' => 200]),
        ]);

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient)->findOffers($isbn, Marketplace::Fr);

        $this->assertNotEmpty($offers);
        $this->assertSame(PriceKindEnum::MerchantLive, $offers[0]->kind);
        $this->assertSame('eBay', $offers[0]->merchant);
        $this->assertSame('EUR', $offers[0]->currency);
        $this->assertIsFloat($offers[0]->amount);
        $this->assertSame('ebay', $offers[0]->source);
    }

    public function testFindOffersUsesAffiliateUrlWhenAvailable(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse($this->oauthFixture(), ['http_code' => 200]),
            new MockResponse($this->browseFixture(), ['http_code' => 200]),
        ]);

        $isbn   = Isbn::fromString('9782723425483');
        $offers = $this->makeProvider($httpClient)->findOffers($isbn, Marketplace::Fr);

        $affiliateOffer = array_filter(
            $offers,
            fn ($o) => $o->url !== null && str_contains((string) $o->url, 'rover.ebay.com'),
        );
        $this->assertNotEmpty($affiliateOffer);
    }

    public function testFindOffersReturnsEmptyWhenClientIdIsEmpty(): void
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

    public function testTokenIsCachedAcrossMultipleCalls(): void
    {
        $oauthCallCount = 0;
        $oauthFixture   = $this->oauthFixture();
        $browseFixture  = $this->browseFixture();

        $httpClient = new MockHttpClient(
            function (string $method, string $url) use (&$oauthCallCount, $oauthFixture, $browseFixture): MockResponse {
                if (str_contains($url, 'oauth2/token')) {
                    $oauthCallCount++;

                    return new MockResponse($oauthFixture, ['http_code' => 200]);
                }

                return new MockResponse($browseFixture, ['http_code' => 200]);
            },
        );

        $isbn          = Isbn::fromString('9782723425483');
        $cache         = new ArrayAdapter();
        $tokenProvider = new EbayOAuthTokenProvider(
            $httpClient,
            self::OAUTH_URL,
            self::CLIENT_ID,
            self::CLIENT_SECRET,
            $cache,
            new NullLogger(),
        );
        $provider = new EbayBrowsePriceProvider($httpClient, $tokenProvider, self::BASE_URL, '', new NullLogger());

        $provider->findOffers($isbn, Marketplace::Fr);
        $provider->findOffers($isbn, Marketplace::Fr);

        $this->assertSame(1, $oauthCallCount);
    }
}
