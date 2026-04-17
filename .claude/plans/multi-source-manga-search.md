# Feature Plan: Multi-Source Manga Search with Fallback Chain

## Context

**Current state:** Jikan (MyAnimeList) is the primary search client. Google Books is wired separately for volume cover enrichment only. No fallback, no source tracking, no tests.

**Target state:** Amazon PA API is primary. Google Books is the automatic fallback. If both fail, a clear 503 domain error is returned. The frontend shows a small source indicator (Amazon or Google logo) next to the search bar.

> **Note for developer:** The Amazon API is the **Product Advertising API 5.0** (PA API). It requires an active Amazon Associates affiliate account and AWS credentials. Confirm this with the product owner before starting Phase 1 — if the associate account isn't ready, Jikan can stay as primary and be swapped later with zero code change.

---

## Phase 1 — Backend: `ExternalMangaDto` source tracking

**File:** `back/src/Manga/Domain/ExternalMangaDto.php`

Add a `source` field:

```php
public string $source = 'unknown', // 'amazon' | 'google' | 'jikan' | 'unknown'
```

Every client sets its own identifier on each DTO it builds. This propagates automatically through the fallback chain with no extra plumbing.

Update all three existing clients to set their value:
- `GoogleBooksMangaApiClient` → `source = 'google'`
- `JikanMangaApiClient` → `source = 'jikan'`
- `NullMangaApiClient` → `source = 'unknown'`

---

## Phase 2 — Backend: `AmazonBooksMangaApiClient`

**New file:** `back/src/Manga/Infrastructure/ExternalApi/AmazonBooksMangaApiClient.php`

- Implements `ExternalApiClientInterface`
- Calls **Amazon PA API 5.0** `SearchItems` operation
- Authentication: AWS4-HMAC-SHA256 signed requests (implement a `signRequest()` private method — see PA API v5 signing guide)
- Search params: `Keywords = $query`, `SearchIndex = Books`, `Marketplace = www.amazon.fr`
- Mapping: ASIN → `externalId`, `ItemInfo.Title` → `title`, `ItemInfo.ByLineInfo.Contributors` → `author`, `ItemInfo.ContentInfo.Languages` to validate French editions, `Images.Primary.Large.URL` → `coverUrl`, `ItemInfo.ContentInfo.PagesCount` for volume estimation
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
            'All search providers are currently unavailable. Please try again later.'
        );
    }

    public function getHttpStatusCode(): int { return 503; }
}
```

The existing `ExceptionListener` will handle it automatically, returning `{"error": "..."}` with a 503.

---

## Phase 4 — Backend: `FallbackExternalApiClient`

**New file:** `back/src/Manga/Infrastructure/ExternalApi/FallbackExternalApiClient.php`

```php
final readonly class FallbackExternalApiClient implements ExternalApiClientInterface
{
    public function __construct(
        private ExternalApiClientInterface $primary,
        private ExternalApiClientInterface $secondary,
    ) {}

    public function searchByTitle(string $query, string $type = 'manga', int $page = 1): array
    {
        try {
            $results = $this->primary->searchByTitle($query, $type, $page);
            if ($results !== []) {
                return $results;
            }
        } catch (\Throwable) {}

        try {
            return $this->secondary->searchByTitle($query, $type, $page);
        } catch (\Throwable) {
            throw new ExternalApiUnavailableException();
        }
    }

    public function getMangaById(string $externalId): ?ExternalMangaDto
    {
        try {
            $result = $this->primary->getMangaById($externalId);
            if ($result !== null) {
                return $result;
            }
        } catch (\Throwable) {}

        try {
            return $this->secondary->getMangaById($externalId);
        } catch (\Throwable) {
            throw new ExternalApiUnavailableException();
        }
    }
}
```

**Fallback trigger conditions:**
- Primary throws any `\Throwable` (network error, auth error, rate limit)
- Primary returns an empty array (zero results → try secondary)

---

## Phase 5 — Backend: `services.yaml` wiring

**File:** `back/config/services.yaml`

```yaml
App\Manga\Domain\ExternalApiClientInterface:
  alias: App\Manga\Infrastructure\ExternalApi\FallbackExternalApiClient

App\Manga\Infrastructure\ExternalApi\FallbackExternalApiClient:
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

Remove or comment out the current Jikan alias. Jikan can be kept in the codebase as an optional client (swap `$primary` to test).

---

## Phase 6 — Backend: Update `SearchExternalMangaHandler`

**File:** `back/src/Manga/Application/SearchExternalMangaHandler.php`

Change the return structure to include `source`:

```php
$results = $this->client->searchByTitle($query->query, $query->type, $query->page);

return [
    'source' => $results !== [] ? $results[0]->source : 'none',
    'results' => array_map(static fn ($dto) => [
        'externalId' => $dto->externalId,
        'title' => $dto->title,
        // ...existing fields...
    ], $results),
];
```

The controller returns this as-is — the existing `JsonResponse` encoding handles it.

---

## Phase 7 — Frontend: Updated types

**File:** `front/src/api/manga.ts` (or wherever `ExternalMangaResult` is defined)

```typescript
export type SearchSource = 'amazon' | 'google' | 'jikan' | 'none' | null

export interface ExternalSearchResponse {
  source: SearchSource
  results: ExternalMangaResult[]
}
```

---

## Phase 8 — Frontend: Update `useExternalSearch.ts`

- Add `currentSource: Ref<SearchSource>` initialized to `null`
- On successful search: set `currentSource.value = res.data.source`
- On `loadMore`: keep existing `currentSource` (source does not reset on pagination)
- On `clear()`: reset `currentSource.value = null`
- On error: set `currentSource.value = null`
- Parse `res.data.results` instead of `res.data`

---

## Phase 9 — Frontend: `SearchSourceBadge.vue` component

**New file:** `front/src/components/atoms/SearchSourceBadge.vue`

- Props: `source: SearchSource`
- Renders a small tooltip-enabled badge to the left of the search input
- `null` / loading → invisible (no layout shift)
- `'amazon'` → Amazon logo SVG + tooltip "Résultats via Amazon"
- `'google'` → Google Books logo SVG + tooltip "Résultats via Google Books"
- `'jikan'` → MyAnimeList logo SVG + tooltip "Résultats via MyAnimeList"
- `'none'` → nothing rendered
- Use DaisyUI `tooltip` class (`data-tip` attribute) for the tooltip
- Logos: embed as inline SVG (small, no external dependency)

---

## Phase 10 — Frontend: Update `AddMangaPage.vue`

In the search bar section of Step 1, add `SearchSourceBadge` to the left of the `<input>`:

```vue
<div class="flex items-center gap-2">
  <SearchSourceBadge :source="currentSource" />
  <input ... />
</div>
```

Expose `currentSource` from `useExternalSearch()` destructuring.

---

## Phase 11 — Tests

### 11.1 Backend unit tests

**`tests/Manga/Infrastructure/ExternalApi/AmazonBooksMangaApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testSearchReturnsExternalMangaDtos` | mock HTTP 200 → DTOs with `source='amazon'` |
| `testSearchReturnsEmptyArrayOnNoResults` | mock HTTP 200 empty items → `[]` |
| `testSearchThrowsOnHttpError` | mock HTTP 500 → `\Throwable` |
| `testSearchThrowsOnNetworkError` | mock transport exception → `\Throwable` |
| `testGetMangaByIdReturnsMappedDto` | mock item detail response → DTO |
| `testGetMangaByIdReturnsNullWhenNotFound` | mock 404 → `null` |

**`tests/Manga/Infrastructure/ExternalApi/FallbackExternalApiClientTest.php`**

| Test | Expected |
|------|----------|
| `testPrimarySuccessReturnsPrimaryResults` | primary returns DTOs → returns them, no secondary call |
| `testPrimaryEmptyCallsSecondary` | primary returns `[]`, secondary returns DTOs → secondary results |
| `testPrimaryExceptionCallsSecondary` | primary throws, secondary returns DTOs → secondary results |
| `testBothFailThrowsUnavailable` | both throw → `ExternalApiUnavailableException` |
| `testPrimaryEmptyAndSecondaryFailsThrowsUnavailable` | primary `[]`, secondary throws → `ExternalApiUnavailableException` |
| `testGetMangaByIdPrimarySuccess` | same pattern for `getMangaById` |
| `testGetMangaByIdPrimaryNullFallback` | primary `null`, secondary returns DTO |
| `testGetMangaByIdBothFailThrows` | both throw → `ExternalApiUnavailableException` |

**`tests/Manga/Application/SearchExternalMangaHandlerTest.php`**

| Test | Expected |
|------|----------|
| `testHandlerReturnsSourceAndResults` | mock client → `{source, results[]}` |
| `testHandlerReturnsNoneSourceOnEmptyResults` | mock returns `[]` → `{source: 'none', results: []}` |
| `testHandlerPropagatesDomainException` | mock throws `ExternalApiUnavailableException` → re-thrown |

### 11.2 Backend functional tests

**`tests/Manga/Infrastructure/Http/MangaSearchExternalTest.php`** (WebTestCase)

| Test | Expected |
|------|----------|
| `testSearchRequiresAuthentication` | no JWT → 401 |
| `testSearchRequiresQParam` | no `?q=` → 400 |
| `testSearchReturnsSourceAndResults` | mock client via test container → 200 `{source, results}` |
| `testSearchReturnsBothApisDownError` | mock client throws `ExternalApiUnavailableException` → 503 `{error: "..."}` |

Use Symfony's `KernelTestCase` + `HttpKernelBrowser`. Override `ExternalApiClientInterface` binding with a mock in the test container.

### 11.3 Frontend unit tests

**`front/src/composables/__tests__/useExternalSearch.test.ts`** (Vitest + `@vue/test-utils`)

| Test | Expected |
|------|----------|
| `initial state` | `results=[]`, `isLoading=false`, `currentSource=null`, `error=null` |
| `search sets loading and calls API` | `isLoading=true` during call, `false` after |
| `search with amazon response` | `currentSource='amazon'`, results populated |
| `search with google response` | `currentSource='google'`, results populated |
| `search returns empty` | `results=[]`, `currentSource='none'` |
| `search on error` | `error` set, `currentSource=null`, toast triggered |
| `503 error shows both-down message` | error message mentions retrying |
| `loadMore appends results and keeps source` | `currentSource` unchanged after page 2 |
| `clear resets all state including source` | all refs reset to initial values |
| `query under 2 chars does not call API` | no HTTP call |

**`front/src/components/atoms/__tests__/SearchSourceBadge.test.ts`**

| Test | Expected |
|------|----------|
| `source=null renders nothing` | no DOM output |
| `source='amazon' renders Amazon logo` | SVG + tooltip text in French |
| `source='google' renders Google logo` | SVG + tooltip text in French |
| `source='none' renders nothing` | no DOM output |

---

## QA Checklist (before merge)

- [ ] `back/.env.example` updated with all four Amazon env vars
- [ ] `make setup` or README updated with Amazon PA API setup instructions
- [ ] Amazon credentials work end-to-end: search returns results, source badge shows Amazon logo
- [ ] Amazon credentials absent/invalid: falls back to Google, badge shows Google logo
- [ ] Amazon returns 0 results for a query: falls back to Google (not treated as error)
- [ ] Both APIs fail (tested with invalid keys for both): frontend shows the 503 message "both providers unavailable"
- [ ] Pagination: source badge stays stable across `loadMore()` calls
- [ ] Source badge invisible while search is loading (no flickering)
- [ ] No regression on the volume cover search (still calls Google Books directly)
- [ ] All CI tests pass (`make test` / PHPStan + Vitest)
- [ ] i18n: all new user-facing strings added to `fr.json` and `en.json`

---

## Out of scope / future consideration

- **True circuit breaker** (open/half-open/closed states with failure rate tracking): the current fallback chain calls both APIs on every failure. A circuit breaker would skip a failing provider for a cooldown window (e.g. 60s) to avoid hammering it. This can be added later using Redis to store circuit state, with negligible changes to `FallbackExternalApiClient`.
- Caching search results in Redis to reduce external API quota usage.
- Source persistence across pages (current design: source is re-evaluated per API call — acceptable for now).
