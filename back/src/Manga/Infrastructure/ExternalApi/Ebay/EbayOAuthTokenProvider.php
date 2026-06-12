<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi\Ebay;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        if ($this->clientId === '') {
            return null;
        }

        try {
            $item = $this->cache->getItem(self::CACHE_KEY);
            if ($item->isHit()) {
                return (string) $item->get();
            }

            return $this->fetchAndCacheToken($item);
        } catch (Throwable $exception) {
            $this->logger->error('EBAY OAUTH : token fetch failed.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchAndCacheToken(\Psr\Cache\CacheItemInterface $item): ?string
    {
        $credentials = base64_encode(sprintf('%s:%s', $this->clientId, $this->clientSecret));

        $response = $this->httpClient->request('POST', $this->oauthUrl, [
            'headers' => [
                'Authorization' => sprintf('Basic %s', $credentials),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials&scope=https://api.ebay.com/oauth/api_scope',
        ]);

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

        $ttl = max(0, ($data['expires_in'] ?? 7200) - 60);
        $item->set($token);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $token;
    }
}
