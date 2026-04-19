# Plan: MangaDex POC — Smart Stateless Fallback Chain

## Context & Goal

Add MangaDex as the primary external API source while keeping Jikan (search/detail) and
Google Books (volume covers) as fallbacks. No circuit breaker, no shared state — just a
clean try-primary-then-fallback strategy with **source-aware routing** to solve the
coherence problem: if a search fell back to Jikan and returned a MAL integer ID, calling
MangaDex with that ID is meaningless. The `FallbackExternalApiClient` detects the ID
format (UUID = MangaDex, integer = MAL/Jikan) and routes cover requests to the correct
provider without needing to store the source anywhere. Log every decision exhaustively.

## The Coherence Problem (and Solution)

```
BAD (naïve chain):
  searchByTitle("Naruto") → MangaDex fails → Jikan returns { externalId: "1535", source: "jikan" }
  getVolumeCovers("1535") → tries MangaDex first → 404/empty → falls through to Google Books ✓
  BUT: MangaDex was tried uselessly and the log is misleading

GOOD (ID-format routing):
  getVolumeCovers("1535")   → isInteger("1535")  → skip MangaDex → Google Books directly
  getVolumeCovers("uuid..") → isUUID("uuid..")   → MangaDex first → Google Books fallback
```

ID format rules:
- UUID regex `/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i`  → MangaDex
- Pure integer `/^\d+$/`                                                               → Jikan/MAL
- Anything else (Google Books alphanumeric)                                            → Google Books

## Scope

**IN scope:**
- `MangaDexApiClient` implementing `ExternalApiClientInterface`
- `FallbackExternalApiClient` wrapping the chain (stateless, no cache dep)
- `ExternalApiClientInterface` extended with `getVolumeCovers(string $externalId): array`
- `JikanMangaApiClient.getVolumeCovers()` → stub returning `[]`
- `GoogleBooksMangaApiClient.getVolumeCovers()` → real implementation searching by ID
- New `GetVolumeCoversQuery` + handler → `GET /api/manga/external/{externalId}/covers`
- `CoverProxyController` updated to allow `uploads.mangadex.org`
- `services.yaml` wired to `FallbackExternalApiClient`
- `back/.env` cleaned up (keep `GOOGLE_BOOKS_API_KEY`, add MangaDex comment)

**OUT of scope:**
- Circuit breaker / failure state / cache dependency
- Authentication with MangaDex
- Any DB migration

## Architecture Overview

```
Search flow:
  GET /api/manga/external?q=...
    → SearchExternalMangaHandler → FallbackExternalApiClient::searchByTitle()
        ① MangaDexApiClient::searchByTitle()
           ✓ results → return (log: "MangaDex OK, N results")
           ✗ exception OR empty → log "MangaDex failed/empty, falling back to Jikan"
        ② JikanMangaApiClient::searchByTitle()
           ✓ results → return (log: "Jikan fallback OK, N results")
           ✗ exception → log "Jikan also failed" → return []

Cover flow (source-aware):
  GET /api/manga/external/{externalId}/covers
    → GetVolumeCoversHandler → FallbackExternalApiClient::getVolumeCovers()
        detect ID format:
          UUID  → ① MangaDex covers → ② Google Books fallback
          int   → skip MangaDex → ① Google Books directly
          other → ① Google Books directly
        each step: log "trying X for id=...", success/failure/fallback reason
```

---

## Backend Steps

### Step 1 — Domain: extend ExternalApiClientInterface
**File:** `back/src/Manga/Domain/ExternalApiClientInterface.php` *(modify)*
**Why:** Unified per-volume cover method so each provider can implement its own strategy
and the fallback chain has a single call site.

```php
<?php

declare(strict_types=1);

namespace App\Manga\Domain;

interface ExternalApiClientInterface
{
    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array;

    public function getMangaById(string $externalId): ?ExternalMangaDto;

    /**
     * Fetch per-volume cover art for an already-known external manga ID.
     * The ID format is provider-specific — callers must pass the ID exactly
     * as it was returned by the same provider's searchByTitle / getMangaById.
     *
     * @return ExternalVolumeDto[]
     */
    public function getVolumeCovers(string $externalId): array;
}
```

---

### Step 2 — Infrastructure: MangaDexApiClient
**File:** `back/src/Manga/Infrastructure/ExternalApi/MangaDexApiClient.php` *(create)*
**Why:** Primary provider — no API key, UUID-based IDs, dedicated cover endpoint per volume.

```php
<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\ExternalVolumeDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class MangaDexApiClient implements ExternalApiClientInterface
{
    private const string BASE_URL    = 'https://api.mangadex.org';
    private const string UPLOADS_URL = 'https://uploads.mangadex.org/covers';
    private const string LOG_PREFIX  = '[MangaDex] ';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: start', [
            'query' => $query, 'type' => $type, 'page' => $page,
        ]);

        $response = $this->httpClient->request('GET', self::BASE_URL . '/manga', [
            'query' => [
                'title'            => $query,
                'limit'            => 20,
                'offset'           => ($page - 1) * 20,
                'includes[]'       => ['cover_art', 'author'],
                'contentRating[]'  => ['safe', 'suggestive', 'erotica'],
                'order[relevance]' => 'desc',
            ],
        ]);

        $data  = $response->toArray();
        $items = $data['data'] ?? [];

        $results = array_values(array_filter(array_map(
            fn (array $item) => $this->mapMangaToDto($item),
            $items,
        )));

        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: done', [
            'query'  => $query,
            'raw'    => count($items),
            'mapped' => count($results),
        ]);

        return $results;
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info(self::LOG_PREFIX . 'getMangaById: start', ['id' => $externalId]);

        $response = $this->httpClient->request(
            'GET',
            self::BASE_URL . '/manga/' . $externalId,
            ['query' => ['includes[]' => ['cover_art', 'author']]],
        );

        $data = $response->toArray();
        $item = $data['data'] ?? null;

        if ($item === null) {
            $this->logger->warning(self::LOG_PREFIX . 'getMangaById: no data in response', ['id' => $externalId]);
            return null;
        }

        $dto = $this->mapMangaToDto($item);
        if ($dto === null) {
            return null;
        }

        $volumes = $this->getVolumeCovers($externalId);

        $this->logger->info(self::LOG_PREFIX . 'getMangaById: done', [
            'id'      => $externalId,
            'title'   => $dto->title,
            'volumes' => count($volumes),
        ]);

        return new ExternalMangaDto(
            externalId:   $dto->externalId,
            title:        $dto->title,
            edition:      $dto->edition,
            author:       $dto->author,
            summary:      $dto->summary,
            coverUrl:     $dto->coverUrl,
            genre:        $dto->genre,
            language:     $dto->language,
            source:       $dto->source,
            totalVolumes: $dto->totalVolumes,
            volumes:      $volumes,
        );
    }

    /** @return ExternalVolumeDto[] */
    public function getVolumeCovers(string $externalId): array
    {
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: start', ['id' => $externalId]);

        $response = $this->httpClient->request('GET', self::BASE_URL . '/cover', [
            'query' => [
                'manga[]'       => [$externalId],
                'limit'         => 100,
                'order[volume]' => 'asc',
            ],
        ]);

        $data    = $response->toArray();
        $volumes = [];

        foreach ($data['data'] ?? [] as $cover) {
            $attrs     = $cover['attributes'] ?? [];
            $volumeNum = isset($attrs['volume']) && $attrs['volume'] !== '' ? (int) $attrs['volume'] : null;
            if ($volumeNum === null) {
                continue;
            }

            $fileName = $attrs['fileName'] ?? null;
            $coverUrl = $fileName !== null
                ? self::UPLOADS_URL . '/' . $externalId . '/' . $fileName . '.512.jpg'
                : null;

            $releaseDate = null;
            if (!empty($attrs['createdAt'])) {
                try {
                    $releaseDate = new \DateTimeImmutable($attrs['createdAt']);
                } catch (Throwable) {}
            }

            $volumes[] = new ExternalVolumeDto(
                number:      $volumeNum,
                coverUrl:    $coverUrl,
                releaseDate: $releaseDate,
            );
        }

        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: done', [
            'id'      => $externalId,
            'volumes' => count($volumes),
        ]);

        return $volumes;
    }

    /** @param array<string, mixed> $item */
    private function mapMangaToDto(array $item): ?ExternalMangaDto
    {
        $id    = $item['id'] ?? null;
        $attrs = $item['attributes'] ?? [];
        if ($id === null) {
            return null;
        }

        $title = $this->extractTitle($attrs);
        if ($title === null) {
            $this->logger->debug(self::LOG_PREFIX . 'mapMangaToDto: skipped (no title)', ['id' => $id]);
            return null;
        }

        $relationships = $item['relationships'] ?? [];

        return new ExternalMangaDto(
            externalId:   $id,
            title:        $title,
            edition:      null,
            author:       $this->extractAuthor($relationships),
            summary:      $this->extractDescription($attrs),
            coverUrl:     $this->extractMainCoverUrl($id, $relationships),
            genre:        $this->extractGenre($attrs),
            language:     'fr',
            source:       'mangadex',
            totalVolumes: isset($attrs['lastVolume']) && $attrs['lastVolume'] !== ''
                ? (int) $attrs['lastVolume']
                : null,
        );
    }

    /** @param array<string, mixed> $attrs */
    private function extractTitle(array $attrs): ?string
    {
        $titles = $attrs['title'] ?? [];
        return $titles['fr'] ?? $titles['en'] ?? $titles['ja-ro']
            ?? (count($titles) > 0 ? array_values($titles)[0] : null);
    }

    /** @param array<string, mixed> $attrs */
    private function extractDescription(array $attrs): ?string
    {
        $desc = $attrs['description'] ?? [];
        return $desc['fr'] ?? $desc['en']
            ?? (count($desc) > 0 ? array_values($desc)[0] : null);
    }

    /**
     * @param array<int, array<string, mixed>> $rels
     */
    private function extractMainCoverUrl(string $mangaId, array $rels): ?string
    {
        foreach ($rels as $rel) {
            if (($rel['type'] ?? '') !== 'cover_art') {
                continue;
            }
            $fileName = $rel['attributes']['fileName'] ?? null;
            if ($fileName !== null) {
                return self::UPLOADS_URL . '/' . $mangaId . '/' . $fileName . '.512.jpg';
            }
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rels
     */
    private function extractAuthor(array $rels): ?string
    {
        $names = [];
        foreach ($rels as $rel) {
            if (($rel['type'] ?? '') !== 'author') {
                continue;
            }
            $name = $rel['attributes']['name'] ?? null;
            if ($name !== null) {
                $names[] = $name;
            }
        }
        return $names !== [] ? implode(', ', $names) : null;
    }

    /** @param array<string, mixed> $attrs */
    private function extractGenre(array $attrs): string
    {
        foreach ($attrs['tags'] ?? [] as $tag) {
            if (($tag['attributes']['group'] ?? '') !== 'demographic') {
                continue;
            }
            $name   = strtolower($tag['attributes']['name']['en'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'shounen') || str_contains($name, 'shonen') => 'shonen',
                str_contains($name, 'shoujo')  || str_contains($name, 'shojo')  => 'shojo',
                str_contains($name, 'seinen')  => 'seinen',
                str_contains($name, 'josei')   => 'josei',
                default                        => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        foreach ($attrs['tags'] ?? [] as $tag) {
            $name   = strtolower($tag['attributes']['name']['en'] ?? '');
            $mapped = match (true) {
                str_contains($name, 'isekai')                                            => 'isekai',
                str_contains($name, 'action')                                            => 'action',
                str_contains($name, 'romance')                                           => 'romance',
                str_contains($name, 'horror')                                            => 'horror',
                str_contains($name, 'fantasy')                                           => 'fantasy',
                str_contains($name, 'sci-fi') || str_contains($name, 'science fiction')  => 'sci_fi',
                str_contains($name, 'sport')                                             => 'sports',
                default                                                                  => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return 'other';
    }
}
```

---

### Step 3 — Infrastructure: `getVolumeCovers()` stub on JikanMangaApiClient
**File:** `back/src/Manga/Infrastructure/ExternalApi/JikanMangaApiClient.php` *(modify)*
**Why:** Jikan has no per-volume cover endpoint — it returns `[]` so the chain routes
cover requests straight to Google Books when the ID is an integer.

```php
public function getVolumeCovers(string $externalId): array
{
    return [];
}
```

---

### Step 4 — Infrastructure: `getVolumeCovers()` on GoogleBooksMangaApiClient
**File:** `back/src/Manga/Infrastructure/ExternalApi/GoogleBooksMangaApiClient.php` *(modify)*
**Why:** Google Books is the final cover fallback. Given a Google Books volume ID, fetch
the base volume to extract the series name, then search for all volumes and parse the
tome number from each title.

```php
/** @return ExternalVolumeDto[] */
public function getVolumeCovers(string $externalId): array
{
    $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: start', ['externalId' => $externalId]);

    try {
        $baseResponse = $this->httpClient->request('GET', self::BASE_URL . '/volumes/' . $externalId, [
            'query' => ['key' => $this->apiKey],
        ]);
        $base = $baseResponse->toArray();
    } catch (\Throwable $e) {
        $this->logger->error(self::PREFIX_LOGGER . 'getVolumeCovers: failed to fetch base volume', [
            'externalId' => $externalId, 'error' => $e->getMessage(),
        ]);
        return [];
    }

    $seriesTitle = $base['volumeInfo']['title'] ?? null;
    if ($seriesTitle === null) {
        $this->logger->warning(self::PREFIX_LOGGER . 'getVolumeCovers: no title on base volume', [
            'externalId' => $externalId,
        ]);
        return [];
    }

    // Strip volume suffix to isolate the series name (e.g. "One Piece, Vol. 12" → "One Piece")
    $seriesName = preg_replace('/[,\s]+(vol|tome|t|volume)\.?\s*\d+.*$/i', '', $seriesTitle) ?? $seriesTitle;

    $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: searching series', [
        'seriesName' => $seriesName,
    ]);

    try {
        $searchResponse = $this->httpClient->request('GET', self::BASE_URL . '/volumes', [
            'query' => [
                'q'          => $seriesName . '+manga',
                'maxResults' => 40,
                'key'        => $this->apiKey,
            ],
        ]);
        $data = $searchResponse->toArray();
    } catch (\Throwable $e) {
        $this->logger->error(self::PREFIX_LOGGER . 'getVolumeCovers: search request failed', [
            'seriesName' => $seriesName, 'error' => $e->getMessage(),
        ]);
        return [];
    }

    $volumes = [];
    foreach ($data['items'] ?? [] as $item) {
        $info      = $item['volumeInfo'] ?? [];
        $title     = $info['title'] ?? '';
        $volumeNum = $this->parseVolumeNumber($title);
        if ($volumeNum === null) {
            continue;
        }

        $coverUrl = $this->extractCoverUrl($info);
        if ($coverUrl === null) {
            continue;
        }

        $releaseDate = null;
        if (!empty($info['publishedDate'])) {
            try {
                $releaseDate = new \DateTimeImmutable($info['publishedDate']);
            } catch (\Throwable) {}
        }

        // Keep the first hit per volume number (relevance order)
        if (!isset($volumes[$volumeNum])) {
            $volumes[$volumeNum] = new \App\Manga\Domain\ExternalVolumeDto(
                number:      $volumeNum,
                coverUrl:    $coverUrl,
                releaseDate: $releaseDate,
            );
        }
    }

    ksort($volumes);
    $result = array_values($volumes);

    $this->logger->info(self::PREFIX_LOGGER . 'getVolumeCovers: done', [
        'externalId' => $externalId,
        'volumes'    => count($result),
    ]);

    return $result;
}

private function parseVolumeNumber(string $title): ?int
{
    // "One Piece, Vol. 12" / "Naruto Tome 7" / "Attack on Titan 3"
    if (preg_match('/(?:vol|tome|t|volume)\.?\s*(\d+)/i', $title, $matches)) {
        return (int) $matches[1];
    }
    // Trailing number: "Dragon Ball Z 5"
    if (preg_match('/[,\s]+(\d{1,3})\s*$/i', $title, $matches)) {
        return (int) $matches[1];
    }
    return null;
}
```

---

### Step 5 — Infrastructure: FallbackExternalApiClient
**File:** `back/src/Manga/Infrastructure/ExternalApi/FallbackExternalApiClient.php` *(create)*
**Why:** Stateless fallback chain — no shared state, no cache. Logs every decision:
attempt, success, failure, fallback trigger, final outcome.

```php
<?php

declare(strict_types=1);

namespace App\Manga\Infrastructure\ExternalApi;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalMangaDto;
use App\Manga\Domain\ExternalVolumeDto;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Stateless fallback chain for external manga APIs.
 *
 * Search/detail : MangaDex (primary) → Jikan (fallback)
 * Volume covers : source-aware routing to avoid incoherent cross-provider ID lookups
 *
 *   UUID   → MangaDex covers first, Google Books on failure/empty
 *   int    → skip MangaDex (MAL/Jikan ID), Google Books directly
 *   other  → Google Books directly (Google Books alphanumeric ID)
 */
final readonly class FallbackExternalApiClient implements ExternalApiClientInterface
{
    private const string LOG_PREFIX = '[FallbackChain] ';

    // UUID v4 pattern (MangaDex IDs)
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private MangaDexApiClient         $mangaDex,
        private JikanMangaApiClient       $jikan,
        private GoogleBooksMangaApiClient $googleBooks,
        private LoggerInterface           $logger,
    ) {}

    /** @return ExternalMangaDto[] */
    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        $this->logger->info(self::LOG_PREFIX . 'searchByTitle: start', [
            'query' => $query, 'type' => $type, 'page' => $page,
        ]);

        // ① Primary: MangaDex
        try {
            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: trying MangaDex');
            $results = $this->mangaDex->searchByTitle($query, $type, $page);

            if ($results !== []) {
                $this->logger->info(self::LOG_PREFIX . 'searchByTitle: MangaDex OK', [
                    'count' => count($results),
                ]);
                return $results;
            }

            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: MangaDex returned empty — falling back to Jikan', [
                'query' => $query,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'searchByTitle: MangaDex threw — falling back to Jikan', [
                'query' => $query,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        // ② Fallback: Jikan
        try {
            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: trying Jikan');
            $results = $this->jikan->searchByTitle($query, $type, $page);

            $this->logger->info(self::LOG_PREFIX . 'searchByTitle: Jikan done', [
                'count' => count($results),
            ]);

            return $results;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'searchByTitle: Jikan also threw — returning empty', [
                'query' => $query,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return [];
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        $this->logger->info(self::LOG_PREFIX . 'getMangaById: start', ['id' => $externalId]);

        // Only attempt MangaDex if the ID looks like a UUID
        if ($this->isMangaDexId($externalId)) {
            try {
                $this->logger->info(self::LOG_PREFIX . 'getMangaById: trying MangaDex (UUID id)');
                $result = $this->mangaDex->getMangaById($externalId);

                if ($result !== null) {
                    $this->logger->info(self::LOG_PREFIX . 'getMangaById: MangaDex OK', [
                        'id' => $externalId, 'title' => $result->title,
                    ]);
                    return $result;
                }

                $this->logger->info(self::LOG_PREFIX . 'getMangaById: MangaDex returned null — falling back to Jikan', [
                    'id' => $externalId,
                ]);
            } catch (Throwable $e) {
                $this->logger->error(self::LOG_PREFIX . 'getMangaById: MangaDex threw — falling back to Jikan', [
                    'id'    => $externalId,
                    'error' => $e->getMessage(),
                    'class' => $e::class,
                ]);
            }
        } else {
            $this->logger->info(self::LOG_PREFIX . 'getMangaById: non-UUID id, skipping MangaDex', [
                'id' => $externalId,
            ]);
        }

        // ② Fallback: Jikan
        try {
            $this->logger->info(self::LOG_PREFIX . 'getMangaById: trying Jikan');
            $result = $this->jikan->getMangaById($externalId);

            $this->logger->info(self::LOG_PREFIX . 'getMangaById: Jikan done', [
                'id'    => $externalId,
                'found' => $result !== null,
            ]);

            return $result;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getMangaById: Jikan also threw — returning null', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return null;
        }
    }

    /** @return ExternalVolumeDto[] */
    public function getVolumeCovers(string $externalId): array
    {
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: start', ['id' => $externalId]);

        if ($this->isMangaDexId($externalId)) {
            // UUID → MangaDex first, Google Books on failure/empty
            return $this->getVolumeCoversForMangaDexId($externalId);
        }

        // Integer (MAL/Jikan) or Google Books alphanumeric → Google Books directly
        $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: non-UUID id → Google Books directly', [
            'id'     => $externalId,
            'reason' => $this->isJikanId($externalId) ? 'Jikan/MAL integer id' : 'Google Books id',
        ]);

        return $this->tryGoogleBooksCovers($externalId);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return ExternalVolumeDto[] */
    private function getVolumeCoversForMangaDexId(string $externalId): array
    {
        // ① MangaDex covers
        try {
            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: trying MangaDex (UUID id)');
            $covers = $this->mangaDex->getVolumeCovers($externalId);

            if ($covers !== []) {
                $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: MangaDex OK', [
                    'id'     => $externalId,
                    'volumes' => count($covers),
                ]);
                return $covers;
            }

            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: MangaDex empty — falling back to Google Books', [
                'id' => $externalId,
            ]);
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getVolumeCovers: MangaDex threw — falling back to Google Books', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        // ② Google Books fallback (note: Google Books ID ≠ MangaDex UUID, so covers
        //    will be found by series-name search, not by ID lookup)
        return $this->tryGoogleBooksCovers($externalId);
    }

    /** @return ExternalVolumeDto[] */
    private function tryGoogleBooksCovers(string $externalId): array
    {
        try {
            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: trying Google Books');
            $covers = $this->googleBooks->getVolumeCovers($externalId);

            $this->logger->info(self::LOG_PREFIX . 'getVolumeCovers: Google Books done', [
                'id'      => $externalId,
                'volumes' => count($covers),
            ]);

            return $covers;
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_PREFIX . 'getVolumeCovers: Google Books also threw — returning empty', [
                'id'    => $externalId,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return [];
        }
    }

    private function isMangaDexId(string $id): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $id);
    }

    private function isJikanId(string $id): bool
    {
        return ctype_digit($id);
    }
}
```

> **Important note on Google Books fallback for MangaDex IDs:** when MangaDex fails and
> the externalId is a UUID, Google Books cannot look up by that ID. `getVolumeCovers`
> on `GoogleBooksMangaApiClient` does an internal lookup by Google Books ID to extract
> the series name. Passing a MangaDex UUID to it will return `[]` gracefully (the HTTP
> call will 404 and the catch block returns `[]`). This is acceptable for the POC — the
> cover fallback degrades to empty rather than crashing.
>
> If full cover fallback for MangaDex → Google Books is needed, the handler should pass
> the manga title alongside the ID. That is out of scope here.

---

### Step 6 — Application: GetVolumeCoversQuery + Handler
**File:** `back/src/Manga/Application/GetVolumeCovers/GetVolumeCoversQuery.php` *(create)*
**Why:** CQRS query for the new `/external/{externalId}/covers` endpoint.

```php
<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumeCovers;

final readonly class GetVolumeCoversQuery
{
    public function __construct(public string $externalId) {}
}
```

**File:** `back/src/Manga/Application/GetVolumeCovers/GetVolumeCoversHandler.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Manga\Application\GetVolumeCovers;

use App\Manga\Domain\ExternalApiClientInterface;
use App\Manga\Domain\ExternalVolumeDto;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetVolumeCoversHandler
{
    public function __construct(private ExternalApiClientInterface $client) {}

    /** @return array<int, array<string, mixed>> */
    public function __invoke(GetVolumeCoversQuery $query): array
    {
        return array_map(
            static fn (ExternalVolumeDto $dto) => [
                'number'      => $dto->number,
                'coverUrl'    => $dto->coverUrl,
                'releaseDate' => $dto->releaseDate?->format('Y-m-d'),
            ],
            $this->client->getVolumeCovers($query->externalId),
        );
    }
}
```

---

### Step 7 — Infrastructure: new route in MangaController
**File:** `back/src/Manga/Infrastructure/Http/MangaController.php` *(modify)*
**Why:** Exposes `GET /api/manga/external/{externalId}/covers`.

Add import:
```php
use App\Manga\Application\GetVolumeCovers\GetVolumeCoversQuery;
```

Add action (place **after** `/external` and **before** `/{id}`):
```php
#[Route('/external/{externalId}/covers', methods: ['GET'])]
public function getVolumeCovers(string $externalId): JsonResponse
{
    return new JsonResponse(
        $this->queryBus->ask(new GetVolumeCoversQuery($externalId))
    );
}
```

---

### Step 8 — Infrastructure: update CoverProxyController
**File:** `back/src/Manga/Infrastructure/Http/CoverProxyController.php` *(modify)*
**Why:** MangaDex cover images come from `uploads.mangadex.org` — must be allowed.

Replace the guard block:
```php
// OLD
if (!$url || !preg_match('#^https://books\.google[a-z.]*/#', $url)) {
    return new Response('', Response::HTTP_BAD_REQUEST);
}

// NEW
$allowed = [
    '#^https://uploads\.mangadex\.org/covers/#',
    '#^https://books\.google[a-z.]*/books/content#',
];
$isAllowed = array_any($allowed, static fn (string $p) => (bool) preg_match($p, $url));
if (!$url || !$isAllowed) {
    return new Response('', Response::HTTP_BAD_REQUEST);
}
```

> `array_any()` requires PHP 8.4 — already in use in this project.

---

### Step 9 — Config: wire FallbackExternalApiClient in services.yaml
**File:** `back/config/services.yaml` *(modify)*
**Why:** Points the interface alias at the fallback chain; removes the old Jikan alias
and the explicit Google Books override for `SearchVolumeExternalHandler`.

Replace:
```yaml
# Old — delete these two blocks:
App\Manga\Domain\ExternalApiClientInterface:
    alias: App\Manga\Infrastructure\ExternalApi\JikanMangaApiClient

App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient:
    arguments:
        $apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'

App\Manga\Application\SearchVolumeExternal\SearchVolumeExternalHandler:
    arguments:
        $googleBooks: '@App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient'
```

With:
```yaml
# External API — stateless fallback chain: MangaDex → Jikan (search) / Google Books (covers)
App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient:
    arguments:
        $apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'

App\Manga\Domain\ExternalApiClientInterface:
    alias: App\Manga\Infrastructure\ExternalApi\FallbackExternalApiClient
```

> `MangaDexApiClient`, `JikanMangaApiClient`, and `FallbackExternalApiClient` are all
> autowired. `SearchVolumeExternalHandler` now receives `FallbackExternalApiClient` via
> the interface alias — no explicit override needed.

---

### Step 10 — Config: update back/.env
**File:** `back/.env` *(modify)*
**Why:** Document MangaDex (no key) alongside the existing Google Books key.

Keep `GOOGLE_BOOKS_API_KEY` unchanged. Add after it:
```dotenv
###> mangadex ###
# MangaDex public API — no API key required
# Rate limit: ~5 req/s. Docs: https://api.mangadex.org/docs/swagger.html
###< mangadex ###
```

---

## Database Migration

No migration needed — this step does not alter the schema.

---

## Frontend Steps

### Step 11 — API layer: add `getVolumeCovers`
**File:** `front/src/api/manga.ts` *(modify)*
**Why:** Exposes the new `GET /api/manga/external/{externalId}/covers` endpoint.

```typescript
/** Fetch per-volume cover art via the fallback chain (MangaDex → Google Books) */
export async function getVolumeCovers(externalId: string): Promise<{
  number: number
  coverUrl: string | null
  releaseDate: string | null
}[]> {
  const res = await client.get(`/manga/external/${externalId}/covers`)
  return res.data
}
```

---

## i18n Keys

No new user-visible strings introduced by this POC.

---

## QA Gates

### 1. PHP Static Analysis (PHPStan)
```bash
docker compose exec back ./vendor/bin/phpstan analyse --memory-limit=512M
```
Expected: `[OK] No errors`

### 2. PHP Code Style (CS Fixer)
```bash
docker compose exec back ./vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec back ./vendor/bin/php-cs-fixer fix
```
Expected: exit code 0

### 3. PHPUnit Tests
```bash
docker compose exec back ./vendor/bin/phpunit
```
Expected: all tests pass.

### 4. Database Migration Check
```bash
docker compose exec back php bin/console doctrine:migrations:status
```
Expected: no pending migrations.

### 5. Frontend Type Check
```bash
docker compose exec app npx tsc --noEmit
```
Expected: no errors.

### 6. Frontend Lint
```bash
docker compose exec app npx eslint src --ext .ts,.vue
```
Expected: no errors.

### 7. Smoke Test (manual)
```
Happy path:
  1. docker compose up -d
  2. GET /api/manga/external?q=Naruto → results with source: mangadex, cover thumbnails
  3. GET /api/manga/external/{mangadexUuid}/covers → volume array with uploads.mangadex.org URLs
  4. GET /proxy/cover?url=https://uploads.mangadex.org/covers/... → proxied image returned

Fallback path (search):
  5. Add "127.0.0.1 api.mangadex.org" to /etc/hosts on the back container
  6. GET /api/manga/external?q=Naruto → logs show "MangaDex threw — falling back to Jikan"
  7. Results appear with source: jikan
  8. Remove the /etc/hosts entry

Fallback path (covers):
  9. GET /api/manga/external/1535/covers  (Jikan/MAL id)
     → logs show "non-UUID id → Google Books directly"
  10. GET /api/manga/external/{uuid}/covers with MangaDex down
     → logs show "MangaDex threw — falling back to Google Books"

ID routing:
  11. Confirm UUID id routes to MangaDex first
  12. Confirm integer id skips MangaDex entirely
  13. Confirm Google Books alphanumeric id goes to Google Books directly
```

---

## Execution Checklist

### Backend
- [ ] Step 1 — Extend `ExternalApiClientInterface` with `getVolumeCovers()`
- [ ] Step 2 — Create `MangaDexApiClient`
- [ ] Step 3 — Add `getVolumeCovers()` stub to `JikanMangaApiClient`
- [ ] Step 4 — Implement `getVolumeCovers()` in `GoogleBooksMangaApiClient`
- [ ] Step 5 — Create `FallbackExternalApiClient` with source-aware routing
- [ ] Step 6 — Create `GetVolumeCoversQuery` + `GetVolumeCoversHandler`
- [ ] Step 7 — Add `/external/{externalId}/covers` route to `MangaController`
- [ ] Step 8 — Update `CoverProxyController` allowlist for `uploads.mangadex.org`
- [ ] Step 9 — Update `services.yaml`
- [ ] Step 10 — Update `back/.env`

### Database
- [ ] No migration needed

### Frontend
- [ ] Step 11 — Add `getVolumeCovers()` to `api/manga.ts`
- [ ] i18n — none required

### QA
- [ ] PHPStan passes
- [ ] CS Fixer passes
- [ ] PHPUnit passes
- [ ] Doctrine migrations status clean
- [ ] TypeScript noEmit passes
- [ ] ESLint passes
- [ ] Smoke test done (happy path + fallback path + ID routing)

### Git
- [ ] All changes on feature branch
- [ ] Single commit (`git commit --amend` if needed)
- [ ] PR created
