# Feature Plan: Multi-Source Cover Search with Fallback Chain

## Context

**Manga search (adding to collection):** Jikan (MyAnimeList) stays as the sole search client. No change here.

**Cover search (volume cover enrichment):** Currently Google Books only. This feature adds Amazon PA API as primary, with Google Books as automatic fallback. If both fail, a 503 domain error is returned.

**Frontend:** A small source badge (Amazon or Google logo) shown next to the cover search input indicates which API provided the cover results.

> **Note for developer:** The Amazon API is the **Product Advertising API 5.0** (PA API). It requires an active Amazon Associates affiliate account and AWS credentials. Confirm this with the product owner before starting — if the associate account isn't ready, Google Books stays as sole cover client until it is.

---

## Scope summary

| Feature | Client |
|---------|--------|
| Manga title search (`GET /api/manga/external`) | Jikan only — unchanged |
| Volume cover search (`GET /api/manga/volume-search`) | Amazon (primary) → Google Books (fallback) |

---

## Phase 1 — Backend: `ExternalMangaDto` source tracking

**File:** `back/src/Manga/Domain/ExternalMangaDto.php`

Add a `source` field:

```php
public string $source = 'unknown', // 'amazon' | 'google' | 'unknown'
```

Update the two cover-search clients to set their value:
- `GoogleBooksMangaApiClient` → `source = 'google'`
- `AmazonBooksMangaApiClient` (new) → `source = 'amazon'`

Jikan and Null clients do not need updating (they are not part of cover search).

---

## Phase 2 — Backend: `AmazonBooksMangaApiClient`

**New file:** `back/src/Manga/Infrastructure/ExternalApi/AmazonBooksMangaApiClient.php`

- Implements `ExternalApiClientInterface`
- Calls **Amazon PA API 5.0** `SearchItems` operation, focused on cover image retrieval
- Authentication: AWS4-HMAC-SHA256 signed requests (implement a `signRequest()` private method — see PA API v5 signing guide)
- Search params: `Keywords = $query`, `SearchIndex = Books`, `Marketplace = www.amazon.fr`, resources include `Images.Primary.Large`
- Mapping: ASIN → `externalId`, `Images.Primary.Large.URL` → `coverUrl`, title/author as available
- Sets `source = 'amazon'`
- Required env vars (add to `back/.env` and `.env.example`):
  ```
  AMAZON_ACCESS_KEY=
  AMAZON_SECRET_KEY=
  AMAZON_PARTNER_TAG=
  AMAZON_MARKETPLACE=www.amazon.fr
  ```

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
        private ExternalApiClientInterface $primary,   // Amazon
        private ExternalApiClientInterface $secondary, // Google Books
    ) {}

    /** @return array{source: string, results: ExternalMangaDto[]} */
    public function search(string $query, int $page = 1): array
    {
        try {
            $results = $this->primary->searchByTitle($query, 'manga', $page);
            if ($results !== []) {
                return ['source' => 'amazon', 'results' => $results];
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
- Primary throws any `\Throwable` (network error, auth error, rate limit)
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
    $primary: '@App\Manga\Infrastructure\ExternalApi\AmazonBooksMangaApiClient'
    $secondary: '@App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient'

App\Manga\Infrastructure\ExternalApi\AmazonBooksMangaApiClient:
  arguments:
    $accessKey: '%env(AMAZON_ACCESS_KEY)%'
    $secretKey: '%env(AMAZON_SECRET_KEY)%'
    $partnerTag: '%env(AMAZON_PARTNER_TAG)%'
    $marketplace: '%env(AMAZON_MARKETPLACE)%'

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
export type CoverSource = 'amazon' | 'google' | 'none' | null

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
- `'amazon'` → Amazon logo SVG + DaisyUI tooltip `data-tip="Couvertures via Amazon"`
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

**`tests/Manga/Infrastructure/ExternalApi/AmazonBooksMangaApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testSearchReturnsExternalMangaDtos` | mock HTTP 200 → DTOs with `source='amazon'` |
| `testSearchReturnsEmptyArrayOnNoResults` | mock HTTP 200 empty → `[]` |
| `testSearchThrowsOnHttpError` | mock HTTP 500 → `\Throwable` |
| `testSearchThrowsOnNetworkError` | mock transport exception → `\Throwable` |
| `testGetMangaByIdReturnsMappedDto` | mock item detail → DTO |
| `testGetMangaByIdReturnsNullWhenNotFound` | mock 404 → `null` |

**`tests/Manga/Infrastructure/ExternalApi/FallbackCoverApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testPrimarySuccessReturnsPrimaryResults` | primary returns DTOs → `{source:'amazon', results:[...]}` |
| `testPrimaryEmptyCallsSecondary` | primary `[]`, secondary returns DTOs → `{source:'google', results:[...]}` |
| `testPrimaryExceptionCallsSecondary` | primary throws, secondary returns DTOs → google results |
| `testBothFailThrowsUnavailable` | both throw → `ExternalApiUnavailableException` |
| `testPrimaryEmptySecondaryFailsThrows` | primary `[]`, secondary throws → `ExternalApiUnavailableException` |

**`tests/Manga/Application/SearchVolumeCoverHandlerTest.php`** (or equivalent)

| Test | Expected |
|------|----------|
| `testHandlerReturnsSourceAndResults` | mock client → `{source, results[]}` |
| `testHandlerReturnsNoneOnEmptyResults` | mock returns `[]` → `{source:'none', results:[]}` |
| `testHandlerPropagatesDomainException` | mock throws `ExternalApiUnavailableException` → re-thrown |

### 11.2 Backend functional tests

**`tests/Manga/Infrastructure/Http/VolumeSearchTest.php`** (WebTestCase)

| Test | Expected |
|------|----------|
| `testSearchRequiresAuthentication` | no JWT → 401 |
| `testSearchRequiresQParam` | no `?q=` → 400 |
| `testSearchReturnsSourceAndResults` | mock client → 200 `{source, results}` |
| `testSearchReturnsBothApisDownError` | mock throws `ExternalApiUnavailableException` → 503 |

### 11.3 Frontend unit tests

**`front/src/composables/__tests__/useCoverSearch.test.ts`** (or the relevant composable name)

| Test | Expected |
|------|----------|
| `initial state` | `results=[]`, `isLoading=false`, `currentCoverSource=null`, `error=null` |
| `search with amazon response` | `currentCoverSource='amazon'`, results populated |
| `search with google response` | `currentCoverSource='google'`, results populated |
| `search returns empty` | `results=[]`, `currentCoverSource='none'` |
| `search on error` | `error` set, `currentCoverSource=null` |
| `503 shows both-down message` | error message mentions retrying |
| `clear resets source` | `currentCoverSource=null` |

**`front/src/components/atoms/__tests__/CoverSourceBadge.test.ts`**

| Test | Expected |
|------|----------|
| `source=null renders nothing` | no DOM output |
| `source='amazon' renders Amazon logo and tooltip` | SVG + French tooltip text |
| `source='google' renders Google logo and tooltip` | SVG + French tooltip text |
| `source='none' renders nothing` | no DOM output |

---

## QA Checklist (before merge)

- [ ] `back/.env.example` updated with all four Amazon env vars
- [ ] Amazon credentials work end-to-end: cover search returns results, badge shows Amazon logo
- [ ] Amazon credentials absent/invalid: falls back to Google, badge shows Google logo
- [ ] Amazon returns 0 results: falls back to Google (not treated as error)
- [ ] Both APIs fail: frontend shows 503 message mentioning retry
- [ ] Jikan manga title search is completely unaffected
- [ ] Badge invisible while loading (no layout shift)
- [ ] All CI tests pass (PHPStan + Vitest)
- [ ] i18n: new strings added to `fr.json` and `en.json`

---

## Out of scope / future consideration

- **True circuit breaker** (open/half-open/closed with cooldown window): current design retries both APIs on every failure. A Redis-backed circuit breaker could skip a failing provider for 60s. Negligible change to `FallbackCoverApiClient` when needed.
- Caching cover search results in Redis to reduce API quota usage.
