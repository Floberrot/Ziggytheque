# Plan — Collection Filtering + Infinite Scroll Pagination

**Status:** Draft  
**Date:** 2026-04-28

---

## Goal

Replace the current "fetch all then filter client-side" approach with server-side filtering and cursor/offset-based pagination. The frontend uses **infinite scroll** (load next page on scroll, no numbered pages). Each page returns **20 items**.

---

## Filters spec

| Filter | API param | Values |
|---|---|---|
| Search | `search` | Title only (substring, case-insensitive). No author, no edition. |
| Genre (type) | `genre` | One of `GenreEnum` values: shonen, shojo, seinen, josei, kodomomuke, isekai, fantasy, action, romance, horror, sci_fi, slice_of_life, sports, other |
| Edition | `edition` | Free string — ILIKE match on `manga.edition` |
| Sort by rating | `sort` | `rating_asc` or `rating_desc` (NULL ratings go last) |
| Reading status | `readingStatus` | One of `ReadingStatusEnum`: not_started, in_progress, completed, on_hold, dropped |
| Suivis only | `followed` | `true` / `false` — filters on `notificationsEnabled = true` |
| Pagination | `page` | Integer ≥ 1, default 1. Limit is always 20. |

All params are optional. Absent = no constraint on that dimension.

---

## Backend changes

### 0. New enum — `CollectionSortEnum`

**File:** `back/src/Collection/Domain/CollectionSortEnum.php`

`sort` has no existing enum. Create one before touching any DTO.

```php
namespace App\Collection\Domain;

enum CollectionSortEnum: string
{
    case RatingAsc  = 'rating_asc';
    case RatingDesc = 'rating_desc';
}
```

---

### 1. `GetCollectionQuery` — add filter params with typed enums

**File:** `back/src/Collection/Application/Get/GetCollectionQuery.php`

All fields that correspond to a domain enum use that enum type, not `string`.
No raw strings for business concepts in application-layer objects.

```php
use App\Collection\Domain\CollectionSortEnum;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\GenreEnum;

final readonly class GetCollectionQuery
{
    public function __construct(
        public ?string $search = null,
        public ?GenreEnum $genre = null,
        public ?string $edition = null,
        public ?ReadingStatusEnum $readingStatus = null,
        public ?CollectionSortEnum $sort = null,
        public bool $followedOnly = false,
        public int $page = 1,
        public int $limit = 20,
    ) {}
}
```

---

### 2a. `CollectionFilterRequest` — new query string DTO with typed enums

**File:** `back/src/Collection/Infrastructure/Http/CollectionFilterRequest.php`

Enum fields use the actual PHP enum type — **not** `?string` + `#[Assert\Choice]`.
Symfony's `#[MapQueryString]` mapper calls `TheEnum::from($value)` automatically; an
invalid value yields a 422 with no extra code. `#[Assert\Choice]` on a string is the
anti-pattern to avoid.

```php
use App\Collection\Domain\CollectionSortEnum;
use App\Collection\Domain\ReadingStatusEnum;
use App\Manga\Domain\GenreEnum;
use Symfony\Component\Validator\Constraints as Assert;

final class CollectionFilterRequest
{
    public ?string $search = null;
    public ?GenreEnum $genre = null;
    public ?string $edition = null;
    public ?ReadingStatusEnum $readingStatus = null;
    public ?CollectionSortEnum $sort = null;
    public bool $followed = false;

    #[Assert\Positive]
    public int $page = 1;
}
```

> Not `final readonly` — `#[MapQueryString]` mutates public properties after construction.

---

### 2b. `CollectionController` — use `#[MapQueryString]`

**File:** `back/src/Collection/Infrastructure/Http/CollectionController.php`

Because `CollectionFilterRequest` already holds typed enums, the controller passes them
straight through to `GetCollectionQuery` — no conversion or validation needed.

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

#[Route('/api/collection', methods: ['GET'])]
public function list(#[MapQueryString] CollectionFilterRequest $request): JsonResponse
{
    $query = new GetCollectionQuery(
        search:        $request->search,
        genre:         $request->genre,
        edition:       $request->edition,
        readingStatus: $request->readingStatus,
        sort:          $request->sort,
        followedOnly:  $request->followed,
        page:          $request->page,
        limit:         20,
    );

    return new JsonResponse(($this->queryBus)($query));
}
```

---

### 3. `CollectionRepositoryInterface` — new method signature

**File:** `back/src/Collection/Domain/CollectionRepositoryInterface.php`

Add:

```php
/** @return array{items: CollectionEntry[], total: int} */
public function findFiltered(GetCollectionQuery $query): array;
```

Keep `findAll()` as-is (used by tests or other handlers — do not delete).

---

### 4. `DoctrineCollectionRepository` — implement `findFiltered`

**File:** `back/src/Collection/Infrastructure/Doctrine/DoctrineCollectionRepository.php`

```php
public function findFiltered(GetCollectionQuery $query): array
{
    $qb = $this->em->createQueryBuilder()
        ->select('ce')
        ->from(CollectionEntry::class, 'ce')
        ->join('ce.manga', 'm');

    if ($query->search !== null && $query->search !== '') {
        $qb->andWhere('LOWER(m.title) LIKE LOWER(:search)')
           ->setParameter('search', '%' . $query->search . '%');
    }

    if ($query->genre !== null) {
        $qb->andWhere('m.genre = :genre')
           ->setParameter('genre', $query->genre->value);
    }

    if ($query->edition !== null && $query->edition !== '') {
        $qb->andWhere('LOWER(m.edition) LIKE LOWER(:edition)')
           ->setParameter('edition', '%' . $query->edition . '%');
    }

    if ($query->readingStatus !== null) {
        $qb->andWhere('ce.readingStatus = :readingStatus')
           ->setParameter('readingStatus', $query->readingStatus->value);
    }

    if ($query->followedOnly) {
        $qb->andWhere('ce.notificationsEnabled = true');
    }

    // Sorting
    match ($query->sort) {
        CollectionSortEnum::RatingAsc  => $qb->orderBy('ce.rating', 'ASC NULLS LAST'),
        CollectionSortEnum::RatingDesc => $qb->orderBy('ce.rating', 'DESC NULLS LAST'),
        default                        => $qb->orderBy('ce.addedAt', 'DESC'),
    };

    // Count total (before pagination)
    $countQb = clone $qb;
    $countQb->select('COUNT(ce.id)');
    $total = (int) $countQb->getQuery()->getSingleScalarResult();

    // Apply pagination
    $offset = ($query->page - 1) * $query->limit;
    $items = $qb
        ->setFirstResult($offset)
        ->setMaxResults($query->limit)
        ->getQuery()
        ->getResult();

    return ['items' => $items, 'total' => $total];
}
```

> Note: `ASC NULLS LAST` / `DESC NULLS LAST` requires Doctrine's `NULLS_LAST` DQL function or the `beberlei/DoctrineExtensions` bundle. If not available, fallback: add a secondary sort on `ce.addedAt DESC` and accept NULLs sort naturally per Postgres default (NULLs last for DESC, NULLs first for ASC). Check if the extension is registered; if not, use `COALESCE(ce.rating, 0)` for asc and `COALESCE(ce.rating, -1)` for desc as a workaround.

---

### 5. `GetCollectionHandler` — delegate to `findFiltered`

**File:** `back/src/Collection/Application/Get/GetCollectionHandler.php`

```php
public function __invoke(GetCollectionQuery $query): array
{
    $result = $this->repository->findFiltered($query);

    return [
        'items' => array_map(fn(CollectionEntry $e) => $e->toArray(), $result['items']),
        'total' => $result['total'],
        'page'  => $query->page,
        'limit' => $query->limit,
    ];
}
```

---

### 6. Response shape (new)

```json
{
  "items": [ ...CollectionEntry[] (20 max)... ],
  "total": 87,
  "page": 1,
  "limit": 20
}
```

`hasMore` can be computed on the frontend: `page * limit < total`.

---

## Frontend changes

### 7. `api/collection.ts` — update `getCollection`

Replace the current `getCollection()` (no params) with:

```ts
export interface CollectionFilters {
  search?: string
  genre?: string
  edition?: string
  readingStatus?: string
  sort?: 'rating_asc' | 'rating_desc'
  followed?: boolean
  page?: number
}

export interface CollectionPage {
  items: CollectionEntry[]
  total: number
  page: number
  limit: number
}

export const getCollection = (filters: CollectionFilters = {}): Promise<CollectionPage> =>
  client.get('/api/collection', { params: filters }).then(r => r.data)
```

---

### 8. `CollectionPage.vue` — rewrite state + data fetching

**Replace** TanStack `useQuery` with TanStack **`useInfiniteQuery`**:

```ts
const filters = reactive<CollectionFilters>({
  search: undefined,
  genre: undefined,
  edition: undefined,
  readingStatus: undefined,
  sort: undefined,
  followed: false,
})

const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading } =
  useInfiniteQuery({
    queryKey: ['collection', filters],
    queryFn: ({ pageParam = 1 }) =>
      getCollection({ ...filters, page: pageParam }),
    getNextPageParam: (lastPage) => {
      const fetched = lastPage.page * lastPage.limit
      return fetched < lastPage.total ? lastPage.page + 1 : undefined
    },
    initialPageParam: 1,
  })

// Flatten pages into one list
const entries = computed(() =>
  data.value?.pages.flatMap(p => p.items) ?? []
)
```

**Search debounce:** use `watchDebounced` from `@vueuse/core` (300 ms) on `filters.search` to avoid firing a request on every keystroke.

**Reset on filter change:** wrap `filters` mutation to also call `queryClient.resetQueries(['collection'])` so the list restarts from page 1.

---

### 9. Infinite scroll trigger

Use `@vueuse/core`'s `useIntersectionObserver` on a sentinel `<div>` placed after the last card:

```vue
<template>
  <div class="grid ...">
    <MangaCard v-for="entry in entries" :key="entry.id" :entry="entry" />
  </div>

  <!-- Sentinel -->
  <div ref="sentinel" class="h-4" />

  <span v-if="isFetchingNextPage">Chargement...</span>
</template>

<script setup>
const sentinel = ref<HTMLElement | null>(null)
useIntersectionObserver(sentinel, ([{ isIntersecting }]) => {
  if (isIntersecting && hasNextPage.value && !isFetchingNextPage.value) {
    fetchNextPage()
  }
})
</script>
```

---

### 10. Filter UI layout

Add a sticky filter bar above the grid (below the stats pills):

```
[ 🔍 Rechercher par titre...         ]
[ Genre ▾ ] [ Edition ▾ ] [ Statut ▾ ] [ ★ Note ▾ ] [ Suivis ⬜ ]
```

- **Search input:** plain text, debounced, clears with ×.
- **Genre select:** dropdown with all `GenreEnum` labels (i18n keys already exist in `fr.json`).
- **Edition select or input:** either a free-text input (ILIKE) or a dropdown of unique editions from the current data.
- **Reading status:** button group or dropdown — labels match current i18n translations (À lire, En cours, Terminé, En pause, Abandonné).
- **Sort by rating:** dropdown — `Meilleure note`, `Moins bonne note`, `Par défaut (date ajout)`.
- **Suivis toggle:** DaisyUI toggle checkbox.

All filters show a small "active" indicator (badge or highlight) when set. A **"Réinitialiser"** button appears when any filter is active.

---

## Tests

### Backend unit test — `GetCollectionQuery`

**File:** `back/tests/Unit/Collection/GetCollectionQueryTest.php`

- Constructs with no args → defaults are correct (page=1, limit=20, followedOnly=false, etc.)
- Constructs with all args → values stored correctly.

### Backend functional test — `GET /api/collection`

**File:** `back/tests/Functional/Collection/GetCollectionFilterTest.php`

Cover:
1. No params → returns paginated response shape (`items`, `total`, `page`, `limit`).
2. `?page=2` → returns correct offset slice.
3. `?search=naruto` → returns only entries whose manga title contains "naruto" (case-insensitive).
4. `?genre=shonen` → returns only shonen entries.
5. `?readingStatus=completed` → returns only completed entries.
6. `?followed=true` → returns only entries with `notificationsEnabled = true`.
7. `?sort=rating_desc` → first item has the highest rating.
8. `?sort=rating_asc` → first item has the lowest rating (or NULL last).
9. All filters combined → intersection of all constraints.
10. Empty result → `{"items":[], "total":0, "page":1, "limit":20}` with HTTP 200.

> Existing `GetCollectionTest.php` tests the old shape — update them to assert the new paginated envelope instead of a bare array.

---

## Global enum audit (run before or alongside this feature)

Existing DTOs and Request classes use `string` + `#[Assert\Choice]` where a typed enum
should be used. Fix all occurrences in the same PR.

### Known violations to fix

| File | Field | Replace with |
|---|---|---|
| `back/src/Collection/Infrastructure/Http/UpdateStatusRequest.php` | `string $status` + `#[Assert\Choice([...])]` | `ReadingStatusEnum $status` — drop the `#[Assert\Choice]` |

### How to find further violations

```bash
# Grep for Assert\Choice inside Infrastructure/Http/ — each hit is a candidate
grep -rn "Assert\\\\Choice" back/src/*/Infrastructure/Http/
```

For every match:
1. Check if the listed choices map 1-to-1 to an existing enum's cases.
2. If yes → replace `string $field` + `#[Assert\Choice]` with `TheEnum $field`.
3. If no enum exists yet → create one in the relevant `Domain/` folder, then apply step 2.

### Rule of thumb

> If you're writing `#[Assert\Choice(choices: [...])]` on a `string` property, you are
> recreating an enum in the wrong layer. Create or reuse a PHP enum instead.

---

## Execution order

1. Backend model (`GetCollectionQuery`)
2. Backend repository method (`findFiltered`)
3. Backend handler (`GetCollectionHandler`)
4. Backend controller (param extraction)
5. Backend tests (unit + functional)
6. Frontend API layer (`collection.ts`)
7. Frontend `CollectionPage.vue` (infinite query + filter state)
8. Frontend filter UI components
9. Frontend infinite scroll sentinel
10. Manual smoke test: verify all filters work, infinite scroll loads next page, `hasMore=false` stops loading.

---

## Out of scope

- Sorting by title, addedAt, ownedCount — not requested. Add later if needed.
- Edition dropdown populated from DB — use free-text ILIKE for now.
- Per-user isolation — currently no multi-user auth, all entries belong to the single gate user.
- URL persistence of filter state (query string sync) — nice-to-have, not required.
