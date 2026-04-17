# Feature Plan: Multi-Source Cover Search with Fallback Chain (Revised)

## Context

**Manga search (adding to collection):** Jikan (MyAnimeList) stays as the sole search client. No change here.

**Cover search (volume cover enrichment):** Currently Google Books only. This feature adds Open Library as primary (gratuit, simple), with Google Books as automatic fallback. If both fail, a 503 domain error is returned.

**Frontend:** A small source badge (Open Library or Google logo) shown next to the cover search input indicates which API provided the cover results.

> **Note:** Open Library est gratuit, pas de credentials requis — juste HTTP GET requests. Beaucoup plus simple qu'Amazon PA API.

---

## Scope summary

| Feature | Client |
|---------|--------|
| Manga title search (`GET /api/manga/external`) | Jikan only — unchanged |
| Volume cover search (`GET /api/manga/volume-search`) | Open Library (primary) → Google Books (fallback) |

---

## Phase 1 — Backend: `ExternalMangaDto` source tracking

**File:** `back/src/Manga/Domain/ExternalMangaDto.php`

Add a `source` field:

```php
public string $source = 'unknown', // 'openlibrary' | 'google' | 'unknown'
```

Update the cover-search clients to set their value:
- `GoogleBooksMangaApiClient` → `source = 'google'`
- `OpenLibraryMangaApiClient` (new) → `source = 'openlibrary'`

---

## Phase 2 — Backend: `OpenLibraryMangaApiClient`

**New file:** `back/src/Manga/Infrastructure/ExternalApi/OpenLibraryMangaApiClient.php`

- Implements `ExternalApiClientInterface`
- Calls **Open Library REST API** (https://openlibrary.org/api/)
- No authentication needed, no API key required
- Search via ISBN or title: `GET /search.json?q={query}&limit=20`
- Cover URL construction: `https://covers.openlibrary.org/b/id/{cover_id}-M.jpg`
- Mapping: OpenLibrary key → `externalId`, constructed cover URL → `coverUrl`, title/author as available
- Sets `source = 'openlibrary'`
- **No env vars needed**

---

## Phase 3 — Backend: `ExternalApiUnavailableException`

**New file:** `back/src/Manga/Domain/Exception/ExternalApiUnavailableException.php`

```php
final class ExternalApiUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'All cover providers are currently unavailable. Please try again later.'
        );
    }

    public function getHttpStatusCode(): int { return 503; }
}
```

The existing `ExceptionListener` handles it automatically → `{"error": "..."}` with a 503.

---

## Phase 4 — Backend: `FallbackCoverApiClient`

**New file:** `back/src/Manga/Infrastructure/ExternalApi/FallbackCoverApiClient.php`

This class is **not** wired to `ExternalApiClientInterface` — it is only used by the volume-cover search path.

```php
final readonly class FallbackCoverApiClient
{
    public function __construct(
        private ExternalApiClientInterface $primary,   // Open Library
        private ExternalApiClientInterface $secondary, // Google Books
    ) {}

    /** @return array{source: string, results: ExternalMangaDto[]} */
    public function search(string $query, int $page = 1): array
    {
        try {
            $results = $this->primary->searchByTitle($query, 'manga', $page);
            if ($results !== []) {
                return ['source' => 'openlibrary', 'results' => $results];
            }
        } catch (\Throwable) {}

        try {
            $results = $this->secondary->searchByTitle($query, 'manga', $page);
            return ['source' => 'google', 'results' => $results];
        } catch (\Throwable) {
            throw new ExternalApiUnavailableException();
        }
    }
}
```

**Fallback trigger conditions:**
- Primary throws any `\Throwable` (network error, rate limit)
- Primary returns an empty array → try secondary

---

## Phase 5 — Backend: `services.yaml` wiring

**File:** `back/config/services.yaml`

```yaml
# Manga title search — Jikan, unchanged
App\Manga\Domain\ExternalApiClientInterface:
  alias: App\Manga\Infrastructure\ExternalApi\JikanMangaApiClient

# Cover search fallback chain
App\Manga\Infrastructure\ExternalApi\FallbackCoverApiClient:
  arguments:
    $primary: '@App\Manga\Infrastructure\ExternalApi\OpenLibraryMangaApiClient'
    $secondary: '@App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient'

App\Manga\Infrastructure\ExternalApi\OpenLibraryMangaApiClient: null

App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient:
  arguments:
    $apiKey: '%env(GOOGLE_BOOKS_API_KEY)%'
```

---

## Phase 6 — Backend: Update the volume-cover search handler/controller

Locate the handler or controller behind `GET /api/manga/volume-search` and inject `FallbackCoverApiClient` instead of `GoogleBooksMangaApiClient` directly.

Change the response to include `source`:

```php
$result = $this->coverClient->search($query->query, $query->page);

return [
    'source'  => $result['source'],
    'results' => array_map(static fn ($dto) => [
        'externalId' => $dto->externalId,
        'coverUrl'   => $dto->coverUrl,
        'title'      => $dto->title,
    ], $result['results']),
];
```

---

## Phase 7 — Frontend: Updated types

**File:** `front/src/api/manga.ts`

```typescript
export type CoverSource = 'openlibrary' | 'google' | 'none' | null

export interface CoverSearchResponse {
  source: CoverSource
  results: ExternalMangaResult[]
}
```

---

## Phase 8 — Frontend: Update the cover search composable

Wherever the cover search (`/api/manga/volume-search`) is called:
- Add `currentCoverSource: Ref<CoverSource>` initialized to `null`
- On successful search: `currentCoverSource.value = res.data.source`
- On `clear()` / error: reset to `null`
- Parse `res.data.results` instead of `res.data`

---

## Phase 9 — Frontend: `CoverSourceBadge.vue` component

**New file:** `front/src/components/atoms/CoverSourceBadge.vue`

- Props: `source: CoverSource`
- `null` → invisible (no layout shift)
- `'openlibrary'` → Open Library logo SVG + DaisyUI tooltip `data-tip="Couvertures via Open Library"`
- `'google'` → Google Books logo SVG + `data-tip="Couvertures via Google Books"`
- `'none'` → nothing rendered
- Logos: inline SVG, no external dependency

---

## Phase 10 — Frontend: Place badge in cover search UI

In the view where volume cover search is displayed, add `CoverSourceBadge` to the left of the search input:

```vue
<div class="flex items-center gap-2">
  <CoverSourceBadge :source="currentCoverSource" />
  <input ... />
</div>
```

---

## Phase 11 — Tests

### 11.1 Backend unit tests

**`tests/Manga/Infrastructure/ExternalApi/OpenLibraryMangaApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testSearchReturnsExternalMangaDtos` | mock HTTP 200 → DTOs with `source='openlibrary'` |
| `testSearchReturnsEmptyArrayOnNoResults` | mock HTTP 200 empty → `[]` |
| `testSearchThrowsOnHttpError` | mock HTTP 500 → `\Throwable` |
| `testSearchThrowsOnNetworkError` | mock transport exception → `\Throwable` |

**`tests/Manga/Infrastructure/ExternalApi/FallbackCoverApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testPrimarySuccessReturnsPrimaryResults` | primary returns DTOs → `{source:'openlibrary', results:[...]}` |
| `testPrimaryEmptyCallsSecondary` | primary `[]`, secondary returns DTOs → `{source:'google', results:[...]}` |
| `testPrimaryExceptionCallsSecondary` | primary throws, secondary returns DTOs → google results |
| `testBothFailThrowsUnavailable` | both throw → `ExternalApiUnavailableException` |

### 11.2 Frontend unit tests

**`front/src/components/atoms/__tests__/CoverSourceBadge.test.ts`**

| Test | Expected |
|------|----------|
| `source=null renders nothing` | no DOM output |
| `source='openlibrary' renders Open Library logo and tooltip` | SVG + French tooltip text |
| `source='google' renders Google logo and tooltip` | SVG + French tooltip text |
| `source='none' renders nothing` | no DOM output |

---

## QA Checklist (before merge)

- [ ] Open Library API works: cover search returns results, badge shows Open Library logo
- [ ] Open Library fails: falls back to Google, badge shows Google logo
- [ ] Open Library returns 0 results: falls back to Google (not treated as error)
- [ ] Both APIs fail: frontend shows 503 message mentioning retry
- [ ] Jikan manga title search is completely unaffected
- [ ] Badge invisible while loading (no layout shift)
- [ ] All CI tests pass (PHPStan + Vitest)
- [ ] i18n: new strings added to `fr.json` and `en.json`

---

## Advantages over Amazon PA API

✅ Gratuit, pas de credentials, pas d'affiliate account requis  
✅ API simple (GET requests uniquement)  
✅ Implémentation plus courte (pas de signing complexe)  
✅ Meilleure couverture (15M+ livres)  
✅ Zéro configuration d'infra

---

## Out of scope / future consideration

- True circuit breaker with cooldown window
- Caching cover search results in Redis
