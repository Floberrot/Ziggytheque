# Feature: Volume Price + Stats Rework

## Context

Replace the PriceCode bounded context (import from PDF) with a direct `price` field on each `Volume`.  
Prices are set per-volume in the manga modal, or batch-set for all volumes in a collection entry at once.  
Stats are extended to show owned value, wishlist value, and total value.  
Genre breakdown becomes a pie chart.

---

## Architecture Summary

| Layer | Change |
|---|---|
| Backend domain | `Volume.priceCode` (FK → price_codes) → `Volume.price` (float, nullable) |
| Backend bounded ctx | Delete entire `PriceCode/` bounded context |
| Backend application | Add `UpdateVolumeCommand.price`, add `BatchSetVolumePriceCommand` |
| Backend stats | Replace `collectionValue` with `ownedValue` / `wishlistValue` / `totalValue` |
| Frontend types | `Volume.priceCode?: PriceCode` → `Volume.price?: number`, update `Stats` |
| Frontend pages | Remove `PriceCodesPage`, add price input in `EnrichVolumeModal`, batch field in `MangaDetailPage` |
| Frontend dashboard | New stat cards + pie chart for genre breakdown |

---

## Phase 1 — Backend: Remove PriceCode, Add Volume.price

### Step 1 — Delete the PriceCode bounded context

Delete every file under `back/src/PriceCode/`:

```
back/src/PriceCode/Domain/PriceCode.php
back/src/PriceCode/Domain/PriceCodeRepositoryInterface.php
back/src/PriceCode/Domain/Exception/PriceCodeAlreadyExistsException.php
back/src/PriceCode/Domain/Exception/PriceCodeNotFoundException.php
back/src/PriceCode/Application/Create/CreatePriceCodeCommand.php
back/src/PriceCode/Application/Create/CreatePriceCodeHandler.php
back/src/PriceCode/Application/Update/UpdatePriceCodeCommand.php
back/src/PriceCode/Application/Update/UpdatePriceCodeHandler.php
back/src/PriceCode/Application/Delete/DeletePriceCodeCommand.php
back/src/PriceCode/Application/Delete/DeletePriceCodeHandler.php
back/src/PriceCode/Application/List/ListPriceCodesQuery.php
back/src/PriceCode/Application/List/ListPriceCodesHandler.php
back/src/PriceCode/Infrastructure/Http/PriceCodeController.php
back/src/PriceCode/Infrastructure/Http/CreatePriceCodeRequest.php
back/src/PriceCode/Infrastructure/Http/UpdatePriceCodeRequest.php
back/src/PriceCode/Infrastructure/Doctrine/DoctrinePriceCodeRepository.php
back/src/PriceCode/Infrastructure/Console/ImportPriceCodesCommand.php
```

Then delete the now-empty directory tree.

---

### Step 2 — Update Volume entity

File: `back/src/Manga/Domain/Volume.php`

**Remove:**
- `#[ORM\ManyToOne(targetEntity: PriceCode::class)] #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] private ?PriceCode $priceCode`
- The `use` import for `PriceCode`

**Add:**
```php
#[ORM\Column(type: 'float', nullable: true)]
private ?float $price = null;
```

**Update constructor** — replace `?PriceCode $priceCode = null` with `?float $price = null`:
```php
public function __construct(
    string $id,
    Manga $manga,
    int $number,
    ?string $coverUrl = null,
    ?float $price = null,
    ?\DateTimeImmutable $releaseDate = null,
) {
    $this->id = $id;
    $this->manga = $manga;
    $this->number = $number;
    $this->coverUrl = $coverUrl;
    $this->price = $price;
    $this->releaseDate = $releaseDate;
}
```

**Add setter:**
```php
public function setPrice(?float $price): void
{
    $this->price = $price;
}
```

**Update `toArray()`** — replace `priceCode` key:
```php
// Before:
'priceCode' => $this->priceCode?->toArray(),
// After:
'price' => $this->price,
```

---

### Step 3 — Update Manga application layer

#### `back/src/Manga/Infrastructure/Http/AddVolumeRequest.php`
Remove the `priceCode` field entirely.

#### `back/src/Manga/Infrastructure/Http/UpdateVolumeRequest.php`
Replace `priceCode` with `price`:
```php
#[Assert\PositiveOrZero]
public readonly ?float $price = null;
```

#### `back/src/Manga/Application/AddVolume/AddVolumeCommand.php`
Remove `priceCode` field if present.

#### `back/src/Manga/Application/AddVolume/AddVolumeHandler.php`
Remove any PriceCode lookup / injection. Construct Volume without price (price starts null).

#### `back/src/Manga/Application/UpdateVolume/UpdateVolumeCommand.php`
Replace `priceCode` with `price`:
```php
public function __construct(
    public readonly string $mangaId,
    public readonly string $volumeId,
    public readonly ?string $coverUrl,
    public readonly ?float $price,
    public readonly ?\DateTimeImmutable $releaseDate,
) {}
```

#### `back/src/Manga/Application/UpdateVolume/UpdateVolumeHandler.php`
Replace PriceCode lookup with direct price set:
```php
// Remove: $priceCode = $this->priceCodeRepository->findByCode($command->priceCode);
// Add:
$volume->setPrice($command->price);
```

Remove the `PriceCodeRepository` dependency injection.

#### `back/src/Manga/Infrastructure/Http/MangaController.php`
In `updateVolume()`, update the `UpdateVolumeCommand` instantiation — replace `priceCode` with `price`.

---

### Step 4 — Add BatchSetVolumePrice command

This endpoint sets the same price on **all volumes** of a manga, using the collection entry as the entry point.

#### `back/src/Collection/Application/BatchSetVolumePrice/BatchSetVolumePriceCommand.php`
```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\BatchSetVolumePrice;

final readonly class BatchSetVolumePriceCommand
{
    public function __construct(
        public string $collectionEntryId,
        public float $price,
    ) {}
}
```

#### `back/src/Collection/Application/BatchSetVolumePrice/BatchSetVolumePriceHandler.php`
```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\BatchSetVolumePrice;

use App\Collection\Domain\CollectionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BatchSetVolumePriceHandler
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
    ) {}

    public function __invoke(BatchSetVolumePriceCommand $command): void
    {
        $entry = $this->collectionRepository->findById($command->collectionEntryId);
        // entry->manga->volumes is OneToMany
        foreach ($entry->getManga()->getVolumes() as $volume) {
            $volume->setPrice($command->price);
        }
        // Doctrine change tracking will flush automatically, or call flush on the entity manager
        // via a shared infrastructure flusher — see existing handlers for the pattern used in this project
    }
}
```

> **Note for new developer:** look at `ToggleVolumeHandler` or `UpdateReadingStatusHandler` to see how flushing is handled in this project (likely via `EntityManagerInterface` or a shared `DoctrineCollectionRepository::save()` method).

---

### Step 5 — Add batch-price endpoint to CollectionController

File: `back/src/Collection/Infrastructure/Http/CollectionController.php`

Add a new request DTO:

**`back/src/Collection/Infrastructure/Http/BatchSetVolumePriceRequest.php`**
```php
<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BatchSetVolumePriceRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\PositiveOrZero]
        public float $price,
    ) {}
}
```

Add method to controller:
```php
#[Route('/{id}/batch-price', methods: ['PATCH'])]
public function batchSetPrice(string $id, #[MapRequestPayload] BatchSetVolumePriceRequest $request): Response
{
    $this->commandBus->dispatch(new BatchSetVolumePriceCommand($id, $request->price));
    return new Response(null, Response::HTTP_NO_CONTENT);
}
```

---

### Step 6 — Update Stats handler

File: `back/src/Stats/Application/GetStats/GetStatsHandler.php`

Replace the `collectionValue` DQL query with three new queries:

```php
// Owned value — sum prices of volumes the user owns
$ownedValue = (float) $this->entityManager
    ->createQuery('
        SELECT COALESCE(SUM(v.price), 0)
        FROM App\Collection\Domain\VolumeEntry ve
        JOIN ve.volume v
        WHERE ve.isOwned = true AND v.price IS NOT NULL
    ')
    ->getSingleScalarResult();

// Wishlist value — sum prices of volumes wished but not owned
$wishlistValue = (float) $this->entityManager
    ->createQuery('
        SELECT COALESCE(SUM(v.price), 0)
        FROM App\Collection\Domain\VolumeEntry ve
        JOIN ve.volume v
        WHERE ve.isWished = true AND ve.isOwned = false AND v.price IS NOT NULL
    ')
    ->getSingleScalarResult();

$totalValue = $ownedValue + $wishlistValue;
```

Update the returned array:
```php
return [
    // ... existing keys ...
    'ownedValue'    => $ownedValue,
    'wishlistValue' => $wishlistValue,
    'totalValue'    => $totalValue,
    // Remove: 'collectionValue'
];
```

---

### Step 7 — Create Doctrine migration

Run the generator from inside the back container:
```bash
docker compose exec back php bin/console doctrine:migrations:diff
```

The generated migration must include:
```sql
-- Remove FK from volumes to price_codes
ALTER TABLE volumes DROP CONSTRAINT fk_volumes_price_code;
ALTER TABLE volumes DROP COLUMN price_code;

-- Add direct price column
ALTER TABLE volumes ADD price DOUBLE PRECISION DEFAULT NULL;

-- Drop price_codes table
DROP TABLE price_codes;
```

Then apply:
```bash
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
```

> **Verify** with `php bin/console doctrine:schema:validate` — should return no mapping errors.

---

## Phase 2 — Frontend: Remove PriceCode

### Step 8 — Delete PriceCode frontend files

```
front/src/api/priceCode.ts         ← delete
front/src/pages/PriceCodesPage.vue ← delete
```

In `front/src/router/index.ts`:
- Remove the `/price-codes` route import and route definition.

In the sidebar component (find it via `grep -r "price-codes" front/src/`):
- Remove the price-codes navigation link.

---

### Step 9 — Update TypeScript types

File: `front/src/types/index.ts`

**Remove** the `PriceCode` interface entirely.

**Update `Volume`:**
```ts
// Before:
priceCode?: PriceCode

// After:
price?: number
```

**Update `VolumeEntry`:**
```ts
// Before:
priceCode?: PriceCode

// After:
price?: number
```

**Update `Stats`:**
```ts
// Before:
collectionValue: number

// After:
ownedValue: number
wishlistValue: number
totalValue: number
```

---

## Phase 3 — Frontend: Add Price UI

### Step 10 — Update API functions

**`front/src/api/manga.ts`**

Update `updateVolume` payload type — add `price?: number`, remove `priceCode`.

**`front/src/api/collection.ts`**

Add batch price function:
```ts
export const batchSetVolumePrice = (
  collectionId: string,
  price: number,
): Promise<void> =>
  client.patch(`/collection/${collectionId}/batch-price`, { price })
```

---

### Step 11 — Add price input in EnrichVolumeModal

File: `front/src/components/organisms/EnrichVolumeModal.vue`

Add a price input field in the template (after the cover section):
```html
<div class="form-control">
  <label class="label">
    <span class="label-text">{{ $t('volume.price') }}</span>
  </label>
  <label class="input input-bordered flex items-center gap-2">
    <span>€</span>
    <input
      type="number"
      step="0.01"
      min="0"
      v-model.number="localPrice"
      @blur="onPriceBlur"
      class="grow"
      placeholder="0.00"
    />
  </label>
</div>
```

Add to `<script setup>`:
```ts
const localPrice = ref<number | null>(props.volume?.price ?? null)

watch(() => props.volume?.price, (v) => { localPrice.value = v ?? null })

const updateVolumeMutation = useMutation({
  mutationFn: ({ price }: { price: number | null }) =>
    updateVolume(props.mangaId, props.volume!.volumeId, { price }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    queryClient.invalidateQueries({ queryKey: ['stats'] })
  },
})

function onPriceBlur() {
  if (props.volume && localPrice.value !== (props.volume.price ?? null)) {
    updateVolumeMutation.mutate({ price: localPrice.value })
  }
}
```

Add i18n keys in `fr.json` and `en.json`:
```json
"volume": {
  "price": "Prix (€)"
}
```

---

### Step 12 — Batch price setter in MangaDetailPage

File: `front/src/pages/MangaDetailPage.vue`

Add a batch price section near the sync panel:
```html
<div class="flex gap-2 items-center">
  <input
    type="number"
    step="0.01"
    min="0"
    v-model.number="batchPrice"
    class="input input-bordered w-32"
    placeholder="Prix €"
  />
  <button
    class="btn btn-secondary"
    :disabled="batchPrice === null"
    @click="onBatchPrice"
  >
    {{ $t('collection.batchSetPrice') }}
  </button>
</div>
```

Add to script:
```ts
import { batchSetVolumePrice } from '@/api/collection'

const batchPrice = ref<number | null>(null)

const batchPriceMutation = useMutation({
  mutationFn: (price: number) => batchSetVolumePrice(props.id, price),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', props.id] })
    queryClient.invalidateQueries({ queryKey: ['stats'] })
    batchPrice.value = null
    // show success toast
  },
})

function onBatchPrice() {
  if (batchPrice.value !== null) {
    batchPriceMutation.mutate(batchPrice.value)
  }
}
```

Add i18n:
```json
"collection": {
  "batchSetPrice": "Appliquer à tous"
}
```

---

### Step 13 — Show price in collection

**`front/src/components/organisms/MangaCard.vue`**

Compute and display the total value of owned volumes:
```ts
const ownedValue = computed(() =>
  props.entry.volumes
    ?.filter(v => v.isOwned && v.price != null)
    .reduce((sum, v) => sum + (v.price ?? 0), 0) ?? null
)
```

> Note: `CollectionEntry` from the list endpoint does not include volumes detail. This may require either:
> (a) adding an `ownedValue` field to `CollectionEntry.toArray()` in the backend (recommended — compute it server-side), or  
> (b) only showing the price on `MangaDetailPage` where the full volume list is loaded.  
> **Recommended approach:** add `ownedValue: float` to `CollectionEntry::toArray()` by joining through `volumeEntries`.

**Backend change for (a)** — `back/src/Collection/Domain/CollectionEntry.php`, update `toArray()`:
```php
$ownedValue = array_sum(
    array_map(
        fn(VolumeEntry $ve) => $ve->isOwned() ? ($ve->getVolume()->getPrice() ?? 0.0) : 0.0,
        $this->volumeEntries->toArray(),
    )
);
// Add to returned array:
'ownedValue' => $ownedValue,
```

Then in `front/src/types/index.ts`, add `ownedValue: number` to `CollectionEntry`.

In `MangaCard.vue` template, add (example, style to taste):
```html
<span v-if="entry.ownedValue > 0" class="badge badge-outline badge-sm">
  {{ entry.ownedValue.toFixed(2) }} €
</span>
```

---

## Phase 4 — Frontend: Dashboard Rework

### Step 14 — Install chart library

```bash
cd front && npm install chart.js vue-chartjs
```

---

### Step 15 — Create PieChart component

Create `front/src/components/molecules/GenrePieChart.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { Pie } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{ breakdown: Record<string, number> }>()

function randomColor(seed: string): string {
  // deterministic from genre name so colors are stable across re-renders
  let hash = 0
  for (let i = 0; i < seed.length; i++) {
    hash = seed.charCodeAt(i) + ((hash << 5) - hash)
  }
  const h = Math.abs(hash) % 360
  return `hsl(${h}, 65%, 55%)`
}

const chartData = computed(() => {
  const labels = Object.keys(props.breakdown)
  return {
    labels,
    datasets: [
      {
        data: Object.values(props.breakdown),
        backgroundColor: labels.map(randomColor),
        borderWidth: 2,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  plugins: {
    legend: { position: 'right' as const },
  },
}
</script>

<template>
  <Pie :data="chartData" :options="chartOptions" />
</template>
```

> The `randomColor` uses a hash of the genre name so the color is always the same for the same genre (not random each render).

---

### Step 16 — Update DashboardPage

File: `front/src/pages/DashboardPage.vue`

**Replace the single `collectionValue` card with three cards:**

```html
<!-- Owned value -->
<div class="stat">
  <div class="stat-title">{{ $t('stats.ownedValue') }}</div>
  <div class="stat-value text-success">{{ stats.ownedValue.toFixed(2) }} €</div>
</div>

<!-- Wishlist value -->
<div class="stat">
  <div class="stat-title">{{ $t('stats.wishlistValue') }}</div>
  <div class="stat-value text-warning">{{ stats.wishlistValue.toFixed(2) }} €</div>
</div>

<!-- Total value -->
<div class="stat">
  <div class="stat-title">{{ $t('stats.totalValue') }}</div>
  <div class="stat-value">{{ stats.totalValue.toFixed(2) }} €</div>
</div>
```

**Replace progress-bar genre breakdown with the pie chart:**

```html
<!-- Remove the progress-bar loop -->

<!-- Add: -->
<div class="card bg-base-200">
  <div class="card-body">
    <h2 class="card-title">{{ $t('stats.genreBreakdown') }}</h2>
    <GenrePieChart :breakdown="stats.genreBreakdown" />
  </div>
</div>
```

Import the component:
```ts
import GenrePieChart from '@/components/molecules/GenrePieChart.vue'
```

Add i18n keys:
```json
"stats": {
  "ownedValue": "Valeur possédée",
  "wishlistValue": "Valeur souhaitée",
  "totalValue": "Valeur totale"
}
```

---

## Checklist for the new developer

### Backend
- [ ] Delete all files in `back/src/PriceCode/`
- [ ] Update `Volume.php`: remove `priceCode` FK, add `price` float field + setter
- [ ] Update `AddVolumeRequest.php` and `UpdateVolumeRequest.php`: remove `priceCode`, add `price`
- [ ] Update `AddVolumeHandler.php` and `UpdateVolumeHandler.php`: remove PriceCode repo dependency
- [ ] Update `MangaController.php`: pass `price` instead of `priceCode` to UpdateVolumeCommand
- [ ] Create `BatchSetVolumePriceCommand.php` + `BatchSetVolumePriceHandler.php`
- [ ] Create `BatchSetVolumePriceRequest.php`
- [ ] Add `batchSetPrice()` to `CollectionController.php`
- [ ] Update `GetStatsHandler.php`: replace `collectionValue` with `ownedValue` / `wishlistValue` / `totalValue`
- [ ] Update `CollectionEntry::toArray()` to include `ownedValue`
- [ ] Run `doctrine:migrations:diff` and review generated SQL
- [ ] Run `doctrine:migrations:migrate`
- [ ] Run `doctrine:schema:validate`
- [ ] Run `php bin/console lint:container` to catch DI errors

### Frontend
- [ ] Delete `front/src/api/priceCode.ts`
- [ ] Delete `front/src/pages/PriceCodesPage.vue`
- [ ] Remove `/price-codes` route and sidebar link
- [ ] Update `types/index.ts`: remove `PriceCode`, update `Volume`, `VolumeEntry`, `Stats`, add `ownedValue` to `CollectionEntry`
- [ ] Update `api/manga.ts`: `updateVolume` payload uses `price`, not `priceCode`
- [ ] Update `api/collection.ts`: add `batchSetVolumePrice`
- [ ] Update `EnrichVolumeModal.vue`: add price input with blur-save mutation
- [ ] Update `MangaDetailPage.vue`: add batch price input + mutation
- [ ] Update `MangaCard.vue`: show `ownedValue`
- [ ] Run `npm install chart.js vue-chartjs` in `front/`
- [ ] Create `GenrePieChart.vue`
- [ ] Update `DashboardPage.vue`: three value cards + pie chart, remove progress bars
- [ ] Update `fr.json` and `en.json` with new i18n keys
- [ ] Run `npm run type-check` — must pass with 0 errors
- [ ] Run `npm run lint` — must pass

---

## Testing

```bash
# Backend
docker compose exec back php bin/phpunit

# Frontend
cd front && npm run test
cd front && npm run type-check

# Manual smoke test
# 1. Open a manga detail — set price on one volume via the modal
# 2. Open manga detail — use batch price to set same price on all volumes
# 3. Check /dashboard — ownedValue, wishlistValue, totalValue cards show correct sums
# 4. Check /dashboard — genre pie chart renders with one color per genre
# 5. Navigate to /price-codes — must 404 or redirect (route deleted)
```
