<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi\Ebay;

use Closure;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

final readonly class EbayOAuthTokenProvider
{
    private const string CACHE_KEY = 'ebay.oauth.token';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $oauthUrl,
        private string $clientId,
        private string $clientSecret,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function getToken(): ?string
    {
        return $this->cachedToken() ?? $this->requestToken()();
    }

    /** The app token while it is cached, without any request; null otherwise (or when eBay is not set up). */
    public function cachedToken(): ?string
    {
        if ($this->clientId === '') {
            return null;
        }

        try {
            $item = $this->cache->getItem(self::CACHE_KEY);

            return $item->isHit() ? (string) $item->get() : null;
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return null;
        }
    }

    /**
     * Sends the OAuth request for a fresh app token without waiting for it: the returned
     * closure reads the answer, caches the token and returns it (null when unavailable).
     *
     * @return Closure(): ?string
     */
    public function requestToken(): Closure
    {
        if ($this->clientId === '') {
            return static fn (): ?string => null;
        }

        try {
            $credentials = base64_encode(sprintf('%s:%s', $this->clientId, $this->clientSecret));

            $response = $this->httpClient->request('POST', $this->oauthUrl, [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', $credentials),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => 'grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope',
            ]);
        } catch (Throwable $exception) {
            $this->logFailure($exception);

            return static fn (): ?string => null;
        }

        return function () use ($response): ?string {
            try {
                return $this->readAndCacheToken($response);
            } catch (Throwable $exception) {
                $this->logFailure($exception);

                return null;
            }
        };
    }

    private function readAndCacheToken(ResponseInterface $response): ?string
    {
        if ($response->getStatusCode() !== 200) {
            $this->logger->error('EBAY OAUTH : non-200 response.', [
                'status' => $response->getStatusCode(),
            ]);

            return null;
        }

        /** @var array{access_token?: string, expires_in?: int} $data */
        $data  = json_decode($response->getContent(), true);
        $token = $data['access_token'] ?? null;

        if ($token === null) {
            return null;
        }

        $this->cacheToken($token, max(0, ($data['expires_in'] ?? 7200) - 60));

        return $token;
    }

    /** A cache outage only costs a new token next time: the one in hand still works. */
    private function cacheToken(string $token, int $ttlSeconds): void
    {
        try {
            $item = $this->cache->getItem(self::CACHE_KEY);
            $item->set($token);
            $item->expiresAfter($ttlSeconds);
            $this->cache->save($item);
        } catch (Throwable $exception) {
            $this->logFailure($exception);
        }
    }

    private function logFailure(Throwable $exception): void
    {
        $this->logger->error('EBAY OAUTH : token fetch failed.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
