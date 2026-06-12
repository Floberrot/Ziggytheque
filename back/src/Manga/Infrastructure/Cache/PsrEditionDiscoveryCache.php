<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\Cache;

use App\Manga\Domain\EditionDiscoveryCacheInterface;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Caches edition-discovery results so a repeated search costs nothing instead of
 * re-fanning out to every catalogue (BnF + DNB + OpenLibrary + Google ×locales + AniList
 * + cover backfill). This is the main defence against request amplification.
 *
 * Non-empty results live 24h; empty results live only 10 min, so a transient upstream
 * failure is not remembered as "no editions" for a whole day.
 */
final readonly class PsrEditionDiscoveryCache implements EditionDiscoveryCacheInterface
{
    private const int TTL = 86400;     // 24h
    private const int EMPTY_TTL = 600; // 10 min

    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function get(string $query, ?string $author, ?string $language): ?array
    {
        try {
            $item = $this->pool->getItem($this->cacheKey($query, $author, $language));
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

    public function put(string $query, ?string $author, ?string $language, array $editions): void
    {
        try {
            $item = $this->pool->getItem($this->cacheKey($query, $author, $language));
            $item->set($editions);
            $item->expiresAfter($editions === [] ? self::EMPTY_TTL : self::TTL);
            $this->pool->save($item);
        } catch (Throwable) {
            // Cache writes are best-effort; never let a failure bubble up.
        }
    }

    private function cacheKey(string $query, ?string $author, ?string $language): string
    {
        return 'editions_discovery.' . md5(
            mb_strtolower(trim($query)) . '|' . ($author ?? '') . '|' . ($language ?? ''),
        );
    }
}
