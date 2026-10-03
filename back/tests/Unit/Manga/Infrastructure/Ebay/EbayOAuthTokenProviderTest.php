<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manga\Infrastructure\Ebay;

use App\Manga\Infrastructure\ExternalApi\Ebay\EbayOAuthTokenProvider;
use App\Tests\Doubles\Http\RecordingResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

final class EbayOAuthTokenProviderTest extends TestCase
{
    private const string OAUTH_URL = 'https://api.ebay.com/identity/v1/oauth2/token';

    private RecordingResponseFactory $upstream;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->upstream = (new RecordingResponseFactory())->answer(
            '/oauth2/token',
            'oauth',
            (string) json_encode(['access_token' => 'fresh-token', 'expires_in' => 7200]),
        );
        $this->cache = new ArrayAdapter();
    }

    public function testNotSetUpMeansNoTokenAndNoRequest(): void
    {
        $provider = $this->provider(clientId: '');

        $this->assertNull($provider->cachedToken());
        $this->assertNull($provider->requestToken()());
        $this->assertNull($provider->getToken());
        $this->assertSame([], $this->upstream->sentSources());
    }

    public function testCachedTokenNeverSendsARequest(): void
    {
        $this->assertNull($this->provider()->cachedToken());

        $this->cacheToken('cached-token');

        $this->assertSame('cached-token', $this->provider()->cachedToken());
        $this->assertSame('cached-token', $this->provider()->getToken());
        $this->assertSame([], $this->upstream->sentSources());
    }

    public function testRequestTokenSendsTheCallWithoutWaitingForIt(): void
    {
        $readToken = $this->provider()->requestToken();

        $this->assertSame(['oauth'], $this->upstream->sentSources());
        $this->assertSame([], $this->upstream->readSources());

        $this->assertSame('fresh-token', $readToken());
        $this->assertSame('fresh-token', $this->provider()->cachedToken());
    }

    public function testGetTokenFetchesAndCachesAFreshOne(): void
    {
        $this->assertSame('fresh-token', $this->provider()->getToken());
        $this->assertSame('fresh-token', $this->provider()->getToken());

        $this->assertSame(['oauth'], $this->upstream->sentSources());
    }

    public function testARefusedTokenIsNull(): void
    {
        $this->upstream->answer('/oauth2/token', 'oauth', '{"error":"invalid_client"}', ['http_code' => 401]);

        $this->assertNull($this->provider()->getToken());
        $this->assertNull($this->provider()->cachedToken());
    }

    public function testAnUnreachableOAuthServerIsNull(): void
    {
        $this->upstream->answer('/oauth2/token', 'oauth', '', ['error' => 'Could not resolve host']);

        $this->assertNull($this->provider()->getToken());
    }

    public function testAnOAuthCallThatCannotBeSentIsNull(): void
    {
        $this->upstream->refuse('/oauth2/token', 'oauth');

        $this->assertNull($this->provider()->getToken());
    }

    public function testATokenStillWorksWhenTheCacheIsDown(): void
    {
        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new RuntimeException('cache down'));

        $provider = new EbayOAuthTokenProvider(
            new MockHttpClient($this->upstream),
            self::OAUTH_URL,
            'client-id',
            'client-secret',
            $cache,
            new NullLogger(),
        );

        $this->assertNull($provider->cachedToken());
        $this->assertSame('fresh-token', $provider->getToken());
    }

    private function provider(string $clientId = 'client-id'): EbayOAuthTokenProvider
    {
        return new EbayOAuthTokenProvider(
            new MockHttpClient($this->upstream),
            self::OAUTH_URL,
            $clientId,
            'client-secret',
            $this->cache,
            new NullLogger(),
        );
    }

    private function cacheToken(string $token): void
    {
        $item = $this->cache->getItem('ebay.oauth.token');
        $item->set($token);
        $this->cache->save($item);
    }
}
