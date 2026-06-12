<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Cache;

use App\Manga\Domain\Isbn;
use App\Manga\Domain\Marketplace;
use App\Manga\Domain\PriceOfferCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final readonly class PsrPriceOfferCache implements PriceOfferCacheInterface
{
    public function __construct(
        private CacheItemPoolInterface $pool,
        private int $ttlSeconds = 86400,
    ) {
    }

    public function get(Isbn $isbn, Marketplace $marketplace): ?array
    {
        try {
            $item = $this->pool->getItem($this->cacheKey($isbn, $marketplace));

            if (!$item->isHit()) {
                return null;
            }

            /** @var list<array<string, mixed>>|null $value */
            $value = $item->get();

            return is_array($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function put(Isbn $isbn, Marketplace $marketplace, array $offers): void
    {
        try {
            $item = $this->pool->getItem($this->cacheKey($isbn, $marketplace));
            $item->set($offers);
            $item->expiresAfter($this->ttlSeconds);
            $this->pool->save($item);
        } catch (Throwable) {
            // Cache writes are best-effort; never let a failure bubble up.
        }
    }

    private function cacheKey(Isbn $isbn, Marketplace $marketplace): string
    {
        return sprintf('price_offers.%s.%s', $isbn->value, $marketplace->value);
    }
}
