# Plan: Volume Status Refactor

## Context & Goal

Refactor the domain model to replace the three boolean flags (`isOwned`, `isWished`, `isRead`) on `VolumeEntry` and the absence of status on `Volume` with proper PHP-backed enums. `isOwned` and `isWished` are mutually exclusive states — they are replaced by a single `VolumeEntryStatusEnum { None, Wished, Owned }`. `isRead` stays as a boolean (orthogonal to ownership). A new `VolumeStatusEnum { Released, Announced }` is added to `Volume` to track whether a publisher has announced a tome but not yet released it (no cover available). Business rule: `Announced` volumes cannot be set to `Owned`. Frontend is updated to remove all "acheté" wording, visually distinguish announced volumes, redesign the modal action buttons, and add a cover deletion feature.

## Scope

**In scope:**
- `VolumeStatusEnum` on `Volume` (`released` | `announced`)
- `VolumeEntryStatusEnum` on `VolumeEntry` (`none` | `wished` | `owned`), replacing `isOwned` + `isWished`
- New CQRS commands: `SetVolumeStatus`, `ToggleVolumeRead`, `DeleteVolumeCover`
- Removal of `ToggleVolume` and `PurchaseVolume` CQRS + HTTP endpoints
- Extension of `UpdateVolumeCommand` to support `status` field
- DB migration with data migration script
- Frontend: types, API layer, all pages/components that reference isOwned/isWished
- Visual differentiation of announced volumes (dashed border, clock icon, "Annoncé" badge)
- Modern pill-style buttons in `EnrichVolumeModal`
- Cover deletion button in `EnrichVolumeModal`
- Remove every occurrence of "acheté" from front

**Out of scope:**
- `WishlistItem.isPurchased` (separate Wishlist bounded context entity — not related to VolumeEntry)
- Notifications system
- Stats gauge changes beyond DQL query updates

---

## Architecture Overview

HTTP PATCH `/api/collection/{id}/volumes/{veId}/status` → `CollectionController::setVolumeStatus` → `SetVolumeStatusCommand` → `SetVolumeStatusHandler` (validates that Announced volumes cannot be Owned, updates `VolumeEntry.status`, calls `autoUpdateReadingStatus`, saves). Similarly for read toggle and cover delete. Frontend pages are the only layer that calls API functions; components receive `VolumeEntry` as props and emit events upward.

---

## Backend Steps

### Step 1 — Domain: create VolumeStatusEnum
**File:** `back/src/Manga/Domain/VolumeStatusEnum.php` *(create)*
**Why:** Encodes the two publication states a Volume can be in.

```php
<?php

declare(strict_types=1);

namespace App\Manga\Domain;

enum VolumeStatusEnum: string
{
    case Released = 'released';
    case Announced = 'announced';
}
```

---

### Step 2 — Domain: create VolumeEntryStatusEnum
**File:** `back/src/Collection/Domain/VolumeEntryStatusEnum.php` *(create)*
**Why:** Replaces the two mutually-exclusive booleans `isOwned` / `isWished` with a single typed value.

```php
<?php

declare(strict_types=1);

namespace App\Collection\Domain;

enum VolumeEntryStatusEnum: string
{
    case None   = 'none';
    case Wished = 'wished';
    case Owned  = 'owned';
}
```

---

### Step 3 — Domain: update Volume entity
**File:** `back/src/Manga/Domain/Volume.php` *(modify)*
**Why:** Adds `status` column and exposes it in `toArray()`.

Add to constructor parameters (after `releaseDate`):
```php
#[ORM\Column(enumType: VolumeStatusEnum::class, options: ['default' => 'released'])]
public VolumeStatusEnum $status = VolumeStatusEnum::Released,
```

Update `toArray()` — add:
```php
'status' => $this->status->value,
```

> `enumType` instructs Doctrine to store the string value in the column and hydrate back to the enum.

---

### Step 4 — Domain: update VolumeEntry entity
**File:** `back/src/Collection/Domain/VolumeEntry.php` *(modify)*
**Why:** Replaces `isOwned: bool` and `isWished: bool` with `status: VolumeEntryStatusEnum`. Keeps `isRead: bool` unchanged.

Replace the two bool parameters with:
```php
#[ORM\Column(enumType: VolumeEntryStatusEnum::class, options: ['default' => 'none'])]
public VolumeEntryStatusEnum $status = VolumeEntryStatusEnum::None,
```

Remove `isOwned` and `isWished` parameters entirely.

Update `toArray()`:
```php
public function toArray(): array
{
    return [
        'id'          => $this->id,
        'volumeId'    => $this->volume->id,
        'number'      => $this->volume->number,
        'coverUrl'    => $this->volume->coverUrl,
        'price'       => $this->volume->price,
        'volumeStatus'=> $this->volume->status->value,
        'status'      => $this->status->value,
        'isRead'      => $this->isRead,
        'review'      => $this->review,
        'rating'      => $this->rating,
    ];
}
```

> `volumeStatus` is the Volume's publication status (released/announced); `status` is the user's tracking status (none/wished/owned).

---

### Step 5 — Domain: update CollectionEntry toArray
**File:** `back/src/Collection/Domain/CollectionEntry.php` *(modify)*
**Why:** `ownedCount` and `wishedCount` still need to be computed, but now from the enum.

Replace the two filter lambdas in `toArray()`:
```php
'ownedCount'  => $this->volumeEntries
    ->filter(fn (VolumeEntry $ve) => $ve->status === VolumeEntryStatusEnum::Owned)
    ->count(),
'readCount'   => $this->volumeEntries
    ->filter(fn (VolumeEntry $ve) => $ve->isRead)
    ->count(),
'wishedCount' => $this->volumeEntries
    ->filter(fn (VolumeEntry $ve) => $ve->status === VolumeEntryStatusEnum::Wished)
    ->count(),
// ownedValue stays:
'ownedValue' => array_sum(array_map(
    fn (VolumeEntry $ve) => $ve->status === VolumeEntryStatusEnum::Owned
        ? ($ve->volume->price ?? 0.0)
        : 0.0,
    $this->volumeEntries->toArray(),
)),
```

---

### Step 6 — Application: create SetVolumeStatusCommand
**File:** `back/src/Collection/Application/SetVolumeStatus/SetVolumeStatusCommand.php` *(create)*
**Why:** Carries the intent to change a VolumeEntry's tracking status.

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\SetVolumeStatus;

use App\Collection\Domain\VolumeEntryStatusEnum;

final readonly class SetVolumeStatusCommand
{
    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
        public VolumeEntryStatusEnum $status,
    ) {}
}
```

---

### Step 7 — Application: create SetVolumeStatusHandler
**File:** `back/src/Collection/Application/SetVolumeStatus/SetVolumeStatusHandler.php` *(create)*
**Why:** Replaces both `ToggleVolumeHandler` (isOwned/isWished) and `PurchaseVolumeHandler`. Enforces the business rule that Announced volumes cannot be Owned.

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\SetVolumeStatus;

use App\Collection\Domain\CollectionEntry;
use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\ReadingStatusEnum;
use App\Collection\Domain\VolumeEntry;
use App\Collection\Domain\VolumeEntryStatusEnum;
use App\Manga\Domain\VolumeStatusEnum;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\DomainException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SetVolumeStatusHandler
{
    public function __construct(private CollectionRepositoryInterface $repository) {}

    public function __invoke(SetVolumeStatusCommand $command): void
    {
        $entry = $this->repository->findById($command->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $volumeEntry = $entry->volumeEntries
            ->filter(fn (VolumeEntry $ve) => $ve->id === $command->volumeEntryId)
            ->first();

        if ($volumeEntry === false) {
            throw new NotFoundException('VolumeEntry', $command->volumeEntryId);
        }

        if (
            $command->status === VolumeEntryStatusEnum::Owned
            && $volumeEntry->volume->status === VolumeStatusEnum::Announced
        ) {
            throw new DomainException('Cannot own an announced volume');
        }

        $volumeEntry->status = $command->status;

        $this->autoUpdateReadingStatus($entry);
        $this->repository->save($entry);
    }

    private function autoUpdateReadingStatus(CollectionEntry $entry): void
    {
        if (\in_array($entry->readingStatus, [ReadingStatusEnum::Dropped, ReadingStatusEnum::OnHold], true)) {
            return;
        }

        $total      = $entry->volumeEntries->count();
        if ($total === 0) {
            return;
        }

        $ownedCount = $entry->volumeEntries
            ->filter(fn (VolumeEntry $ve) => $ve->status === VolumeEntryStatusEnum::Owned)
            ->count();
        $readCount  = $entry->volumeEntries
            ->filter(fn (VolumeEntry $ve) => $ve->isRead)
            ->count();

        $entry->readingStatus = match (true) {
            $readCount === $total             => ReadingStatusEnum::Completed,
            $readCount > 0 || $ownedCount > 0 => ReadingStatusEnum::InProgress,
            default                           => ReadingStatusEnum::NotStarted,
        };
    }
}
```

> Check that `App\Shared\Domain\Exception\DomainException` exists (or use the base `\DomainException` — look at ExceptionListener to confirm which class is caught).

---

### Step 8 — Application: create ToggleVolumeReadCommand
**File:** `back/src/Collection/Application/ToggleVolumeRead/ToggleVolumeReadCommand.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolumeRead;

final readonly class ToggleVolumeReadCommand
{
    public function __construct(
        public string $collectionEntryId,
        public string $volumeEntryId,
    ) {}
}
```

---

### Step 9 — Application: create ToggleVolumeReadHandler
**File:** `back/src/Collection/Application/ToggleVolumeRead/ToggleVolumeReadHandler.php` *(create)*
**Why:** Toggles `isRead` in isolation, decoupled from the status enum.

```php
<?php

declare(strict_types=1);

namespace App\Collection\Application\ToggleVolumeRead;

use App\Collection\Domain\CollectionRepositoryInterface;
use App\Collection\Domain\VolumeEntry;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ToggleVolumeReadHandler
{
    public function __construct(private CollectionRepositoryInterface $repository) {}

    public function __invoke(ToggleVolumeReadCommand $command): void
    {
        $entry = $this->repository->findById($command->collectionEntryId);
        if ($entry === null) {
            throw new NotFoundException('CollectionEntry', $command->collectionEntryId);
        }

        $volumeEntry = $entry->volumeEntries
            ->filter(fn (VolumeEntry $ve) => $ve->id === $command->volumeEntryId)
            ->first();

        if ($volumeEntry === false) {
            throw new NotFoundException('VolumeEntry', $command->volumeEntryId);
        }

        $volumeEntry->isRead = !$volumeEntry->isRead;

        $this->repository->save($entry);
    }
}
```

---

### Step 10 — Infrastructure: create SetVolumeStatusRequest
**File:** `back/src/Collection/Infrastructure/Http/SetVolumeStatusRequest.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use App\Collection\Domain\VolumeEntryStatusEnum;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetVolumeStatusRequest
{
    public function __construct(
        #[Assert\NotNull]
        public VolumeEntryStatusEnum $status,
    ) {}
}
```

---

### Step 11 — Infrastructure: update CollectionController
**File:** `back/src/Collection/Infrastructure/Http/CollectionController.php` *(modify)*
**Why:** Remove `toggleVolume` and `purchaseVolume` routes; add `setVolumeStatus` and `toggleVolumeRead`.

Remove imports:
```php
use App\Collection\Application\PurchaseVolume\PurchaseVolumeCommand;
use App\Collection\Application\ToggleVolume\ToggleVolumeCommand;
```

Add imports:
```php
use App\Collection\Application\SetVolumeStatus\SetVolumeStatusCommand;
use App\Collection\Application\ToggleVolumeRead\ToggleVolumeReadCommand;
```

Replace the two removed actions with:
```php
#[Route('/{id}/volumes/{volumeEntryId}/status', methods: ['PATCH'])]
public function setVolumeStatus(
    string $id,
    string $volumeEntryId,
    #[MapRequestPayload] SetVolumeStatusRequest $request,
): JsonResponse {
    $this->commandBus->dispatch(new SetVolumeStatusCommand($id, $volumeEntryId, $request->status));

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}

#[Route('/{id}/volumes/{volumeEntryId}/read', methods: ['PATCH'])]
public function toggleVolumeRead(string $id, string $volumeEntryId): JsonResponse
{
    $this->commandBus->dispatch(new ToggleVolumeReadCommand($id, $volumeEntryId));

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
```

Remove the methods `toggleVolume` and `purchaseVolume` entirely.

---

### Step 12 — Application: delete obsolete ToggleVolume CQRS
**Files to delete:**
- `back/src/Collection/Application/ToggleVolume/ToggleVolumeCommand.php`
- `back/src/Collection/Application/ToggleVolume/ToggleVolumeHandler.php`
- `back/src/Collection/Infrastructure/Http/ToggleVolumeRequest.php`

**Files to delete:**
- `back/src/Collection/Application/PurchaseVolume/PurchaseVolumeCommand.php`
- `back/src/Collection/Application/PurchaseVolume/PurchaseVolumeHandler.php`

```bash
rm back/src/Collection/Application/ToggleVolume/ToggleVolumeCommand.php
rm back/src/Collection/Application/ToggleVolume/ToggleVolumeHandler.php
rm -rf back/src/Collection/Application/ToggleVolume
rm back/src/Collection/Infrastructure/Http/ToggleVolumeRequest.php
rm -rf back/src/Collection/Application/PurchaseVolume
```

---

### Step 13 — Application: update AddRemainingToWishlistHandler
**File:** `back/src/Collection/Application/AddRemainingToWishlist/AddRemainingToWishlistHandler.php` *(modify)*
**Why:** Replace `!$volumeEntry->isOwned` with enum comparison.

```php
foreach ($entry->volumeEntries as $volumeEntry) {
    /** @var VolumeEntry $volumeEntry */
    if ($volumeEntry->status !== VolumeEntryStatusEnum::Owned) {
        $volumeEntry->status = VolumeEntryStatusEnum::Wished;
    }
}
```

Add import: `use App\Collection\Domain\VolumeEntryStatusEnum;`

---

### Step 14 — Application: update ClearWishlistHandler
**File:** `back/src/Collection/Application/ClearWishlist/ClearWishlistHandler.php` *(modify)*

```php
foreach ($entry->volumeEntries as $volumeEntry) {
    /** @var VolumeEntry $volumeEntry */
    if ($volumeEntry->status === VolumeEntryStatusEnum::Wished) {
        $volumeEntry->status = VolumeEntryStatusEnum::None;
    }
}
```

Add import: `use App\Collection\Domain\VolumeEntryStatusEnum;`

---

### Step 15 — Application: update GetStatsHandler
**File:** `back/src/Stats/Application/GetStats/GetStatsHandler.php` *(modify)*
**Why:** DQL queries reference `ve.isOwned` and `ve.isWished` which no longer exist; replace with enum value comparisons.

```php
$totalOwned = (int) $this->em->createQueryBuilder()
    ->select('COUNT(ve.id)')
    ->from(VolumeEntry::class, 've')
    ->where('ve.status = :status')
    ->setParameter('status', VolumeEntryStatusEnum::Owned)
    ->getQuery()
    ->getSingleScalarResult();

$totalRead = (int) $this->em->createQueryBuilder()
    ->select('COUNT(ve.id)')
    ->from(VolumeEntry::class, 've')
    ->where('ve.isRead = true')
    ->getQuery()
    ->getSingleScalarResult();

$totalWishlist = (int) $this->em->createQueryBuilder()
    ->select('COUNT(ve.id)')
    ->from(VolumeEntry::class, 've')
    ->where('ve.status = :status')
    ->setParameter('status', VolumeEntryStatusEnum::Wished)
    ->getQuery()
    ->getSingleScalarResult();

$ownedValue = (float) ($this->em->createQueryBuilder()
    ->select('SUM(v.price)')
    ->from(VolumeEntry::class, 've')
    ->join('ve.volume', 'v')
    ->where('ve.status = :status')
    ->setParameter('status', VolumeEntryStatusEnum::Owned)
    ->andWhere('v.price IS NOT NULL')
    ->getQuery()
    ->getSingleScalarResult() ?? 0);

$wishlistValue = (float) ($this->em->createQueryBuilder()
    ->select('SUM(v.price)')
    ->from(VolumeEntry::class, 've')
    ->join('ve.volume', 'v')
    ->where('ve.status = :status')
    ->setParameter('status', VolumeEntryStatusEnum::Wished)
    ->andWhere('v.price IS NOT NULL')
    ->getQuery()
    ->getSingleScalarResult() ?? 0);
```

Add imports:
```php
use App\Collection\Domain\VolumeEntryStatusEnum;
```

---

### Step 16 — Application: add DeleteVolumeCoverCommand
**File:** `back/src/Manga/Application/DeleteVolumeCover/DeleteVolumeCoverCommand.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Manga\Application\DeleteVolumeCover;

final readonly class DeleteVolumeCoverCommand
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
    ) {}
}
```

---

### Step 17 — Application: add DeleteVolumeCoverHandler
**File:** `back/src/Manga/Application/DeleteVolumeCover/DeleteVolumeCoverHandler.php` *(create)*

```php
<?php

declare(strict_types=1);

namespace App\Manga\Application\DeleteVolumeCover;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Volume;
use App\Shared\Domain\Exception\NotFoundException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteVolumeCoverHandler
{
    public function __construct(private MangaRepositoryInterface $mangaRepository) {}

    public function __invoke(DeleteVolumeCoverCommand $command): void
    {
        $manga = $this->mangaRepository->findById($command->mangaId);
        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $volume = $manga->volumes
            ->filter(fn (Volume $v) => $v->id === $command->volumeId)
            ->first();

        if ($volume === false) {
            throw new NotFoundException('Volume', $command->volumeId);
        }

        $volume->coverUrl = null;

        $this->mangaRepository->save($manga);
    }
}
```

---

### Step 18 — Application: extend UpdateVolumeCommand to support status
**File:** `back/src/Manga/Application/UpdateVolume/UpdateVolumeCommand.php` *(modify)*
**Why:** Allows setting a volume's publication status (released/announced) via the existing PATCH endpoint.

```php
final readonly class UpdateVolumeCommand
{
    public function __construct(
        public string $mangaId,
        public string $volumeId,
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        public ?float $price = null,
        public ?string $status = null,
    ) {}
}
```

---

### Step 19 — Application: update UpdateVolumeHandler to handle status
**File:** `back/src/Manga/Application/UpdateVolume/UpdateVolumeHandler.php` *(modify)*
**Why:** Map the string `status` to the enum and assign it.

Add at end of handler body (before `$this->mangaRepository->save($manga)`):
```php
if ($command->status !== null) {
    $volume->status = VolumeStatusEnum::from($command->status);
}
```

Add import: `use App\Manga\Domain\VolumeStatusEnum;`

---

### Step 20 — Infrastructure: update UpdateVolumeRequest
**File:** `back/src/Manga/Infrastructure/Http/UpdateVolumeRequest.php` *(modify)*
**Why:** Accept the new `status` field from frontend.

```php
final readonly class UpdateVolumeRequest
{
    public function __construct(
        public ?string $coverUrl = null,
        public ?string $releaseDate = null,
        #[Assert\PositiveOrZero]
        public ?float $price = null,
        #[Assert\Choice(choices: ['released', 'announced'])]
        public ?string $status = null,
    ) {}
}
```

---

### Step 21 — Infrastructure: update MangaController
**File:** `back/src/Manga/Infrastructure/Http/MangaController.php` *(modify)*
**Why:** Wire the `status` field from request to command, and add the delete-cover endpoint.

Update `updateVolume` action:
```php
$this->commandBus->dispatch(new UpdateVolumeCommand(
    mangaId: $id,
    volumeId: $volumeId,
    coverUrl: $request->coverUrl,
    releaseDate: $request->releaseDate,
    price: $request->price,
    status: $request->status,
));
```

Add new action:
```php
#[Route('/{id}/volumes/{volumeId}/cover', methods: ['DELETE'])]
public function deleteVolumeCover(string $id, string $volumeId): JsonResponse
{
    $this->commandBus->dispatch(new DeleteVolumeCoverCommand(
        mangaId: $id,
        volumeId: $volumeId,
    ));

    return new JsonResponse(null, Response::HTTP_NO_CONTENT);
}
```

Add import: `use App\Manga\Application\DeleteVolumeCover\DeleteVolumeCoverCommand;`

---

## Database Migration

**Why:** Replace the two boolean columns `is_owned` and `is_wished` on `volume_entries` with a `status` VARCHAR enum column. Add `status` to `volumes`.

```bash
# 1. Generate the diff
docker compose exec back php bin/console doctrine:migrations:diff
```

The generated migration will add the new columns and drop the old ones. **Before applying, open the generated file** and manually insert the data migration between the ADD and DROP statements:

```php
// In the generated up() method, INSERT BEFORE the DROP COLUMN statements:
$this->addSql("UPDATE volume_entries SET status = 'owned' WHERE is_owned = true");
$this->addSql("UPDATE volume_entries SET status = 'wished' WHERE is_wished = true AND is_owned = false");
```

```bash
# 2. Apply migration
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
```

> The `status` column on `volume_entries` defaults to `'none'`, so rows where both booleans were false will naturally map correctly without needing an explicit UPDATE.

---

## Frontend Steps

### Step 22 — Types: update VolumeEntry and add status types
**File:** `front/src/types/index.ts` *(modify)*
**Why:** Reflect the new backend shape.

Replace:
```typescript
export type VolumeToggleField = 'isOwned' | 'isRead' | 'isWished'

export interface VolumeEntry {
  id: string
  volumeId: string
  number: number
  coverUrl: string | null
  price: number | null
  isOwned: boolean
  isRead: boolean
  isWished: boolean
  review: string | null
  rating: number | null
}
```

With:
```typescript
export type VolumeStatus = 'released' | 'announced'
export type VolumeEntryStatus = 'none' | 'wished' | 'owned'

export interface VolumeEntry {
  id: string
  volumeId: string
  number: number
  coverUrl: string | null
  price: number | null
  volumeStatus: VolumeStatus
  status: VolumeEntryStatus
  isRead: boolean
  review: string | null
  rating: number | null
}
```

Also update `Volume` interface — add `status: VolumeStatus`.

---

### Step 23 — API: update collection.ts
**File:** `front/src/api/collection.ts` *(modify)*
**Why:** Replace `toggleVolume` + `purchaseVolume` with the two new endpoints.

Remove: `toggleVolume`, `purchaseVolume` functions. Remove import of `VolumeToggleField`.

Add:
```typescript
import type { VolumeEntryStatus } from '@/types'

export async function setVolumeStatus(
  collectionId: string,
  volumeEntryId: string,
  status: VolumeEntryStatus,
): Promise<void> {
  await client.patch(`/collection/${collectionId}/volumes/${volumeEntryId}/status`, { status })
}

export async function toggleVolumeRead(
  collectionId: string,
  volumeEntryId: string,
): Promise<void> {
  await client.patch(`/collection/${collectionId}/volumes/${volumeEntryId}/read`)
}
```

---

### Step 24 — API: update manga.ts
**File:** `front/src/api/manga.ts` *(modify)*
**Why:** Add cover deletion endpoint and accept `status` in updateVolume.

Update `updateVolume` signature to accept `status`:
```typescript
export async function updateVolume(
  mangaId: string,
  volumeId: string,
  payload: { coverUrl?: string; releaseDate?: string; price?: number; status?: string },
): Promise<void> {
  await client.patch(`/manga/${mangaId}/volumes/${volumeId}`, payload)
}
```

Add:
```typescript
export async function deleteVolumeCover(mangaId: string, volumeId: string): Promise<void> {
  await client.delete(`/manga/${mangaId}/volumes/${volumeId}/cover`)
}
```

---

### Step 25 — Component: rewrite EnrichVolumeModal.vue
**File:** `front/src/components/organisms/EnrichVolumeModal.vue` *(modify)*
**Why:** Replace the three toggle/purchase mutations with two new ones; redesign action buttons as modern pills; add announced badge + delete cover button.

**Script changes:**

Replace imports:
```typescript
import { setVolumeStatus, toggleVolumeRead } from '@/api/collection'
import { updateVolume, searchVolumeExternal, deleteVolumeCover } from '@/api/manga'
import type { VolumeEntry, VolumeEntryStatus } from '@/types'
```

Replace the three mutations (`toggleMutation`, `purchaseMutation`, keep `enrichMutation`) with:
```typescript
const setStatusMutation = useMutation({
  mutationFn: (status: VolumeEntryStatus) =>
    setVolumeStatus(props.collectionEntryId, props.volume!.id, status),
  onSuccess: (_, status) => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    const msg = status === 'owned'
      ? 'Tome marqué comme possédé'
      : status === 'wished'
        ? 'Ajouté à la liste de souhaits'
        : 'Retiré du suivi'
    ui.addToast(msg, 'success')
  },
})

const toggleReadMutation = useMutation({
  mutationFn: () => toggleVolumeRead(props.collectionEntryId, props.volume!.id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})

const deleteCoverMutation = useMutation({
  mutationFn: () => deleteVolumeCover(props.mangaId, props.volume!.volumeId),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    ui.addToast('Couverture supprimée', 'success')
  },
})
```

Update `volumeStatus` computed:
```typescript
const volumeStatus = computed(() => props.volume?.status ?? 'none')
const isAnnounced = computed(() => props.volume?.volumeStatus === 'announced')
```

**Template changes for the left action panel** — replace the current `<div class="flex flex-col gap-2 flex-1 justify-center sm:justify-start">` block with:

```html
<!-- Status + actions (beside cover on mobile, below on desktop) -->
<div class="flex flex-col gap-3 flex-1 justify-center sm:justify-start">

  <!-- Announced badge -->
  <div v-if="isAnnounced" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-base-300/60 border border-base-content/15 w-fit">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-base-content/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span class="text-xs font-semibold text-base-content/50">Annoncé</span>
  </div>

  <!-- Status pill group -->
  <div class="flex flex-col gap-1.5">

    <!-- None → Wished transition button -->
    <button
      v-if="volumeStatus === 'none'"
      class="group flex items-center gap-2 px-3 py-2 rounded-xl border border-warning/40 bg-warning/5 hover:bg-warning/15 hover:border-warning/70 transition-all duration-150 text-sm font-semibold text-warning/80 hover:text-warning w-full"
      :class="{ 'opacity-50 pointer-events-none': setStatusMutation.isPending.value }"
      @click="setStatusMutation.mutate('wished')"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
      </svg>
      Souhaiter
    </button>

    <!-- Wished state: two buttons (mark owned / remove from wishlist) -->
    <template v-else-if="volumeStatus === 'wished'">
      <button
        v-if="!isAnnounced"
        class="group flex items-center gap-2 px-3 py-2 rounded-xl border border-success/50 bg-success/10 hover:bg-success/20 hover:border-success/80 transition-all duration-150 text-sm font-semibold text-success/80 hover:text-success w-full"
        :class="{ 'opacity-50 pointer-events-none': setStatusMutation.isPending.value }"
        @click="setStatusMutation.mutate('owned')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        Marquer possédé
      </button>
      <button
        class="group flex items-center gap-2 px-3 py-2 rounded-xl border border-base-300 bg-base-200/50 hover:bg-base-200 hover:border-base-content/20 transition-all duration-150 text-sm font-medium text-base-content/50 hover:text-base-content/80 w-full"
        :class="{ 'opacity-50 pointer-events-none': setStatusMutation.isPending.value }"
        @click="setStatusMutation.mutate('none')"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Retirer des souhaits
      </button>
    </template>

    <!-- Owned state: current status badge + remove button -->
    <template v-else-if="volumeStatus === 'owned'">
      <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-success/15 border border-success/40">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-success shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <span class="text-sm font-semibold text-success">Possédé</span>
      </div>
      <button
        class="group flex items-center gap-2 px-3 py-2 rounded-xl border border-base-300 bg-base-200/50 hover:bg-base-200 hover:border-base-content/20 transition-all duration-150 text-xs font-medium text-base-content/40 hover:text-base-content/60 w-full"
        :class="{ 'opacity-50 pointer-events-none': setStatusMutation.isPending.value }"
        @click="setStatusMutation.mutate('none')"
      >
        Retirer de la collection
      </button>
    </template>

    <!-- Read toggle (only when owned) -->
    <button
      v-if="volumeStatus === 'owned'"
      class="flex items-center gap-2 w-full px-3 py-2 rounded-xl border transition-all duration-150 text-sm font-medium"
      :class="volume!.isRead
        ? 'bg-info/15 border-info/40 text-info hover:bg-info/25'
        : 'bg-base-200/60 border-base-300 text-base-content/50 hover:bg-base-200'"
      :disabled="toggleReadMutation.isPending.value"
      @click="toggleReadMutation.mutate()"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
      <span class="flex-1 text-left">{{ volume!.isRead ? 'Lu' : 'Non lu' }}</span>
      <div class="w-8 h-4 rounded-full transition-colors relative shrink-0" :class="volume!.isRead ? 'bg-info' : 'bg-base-300'">
        <div class="absolute top-0.5 w-3 h-3 rounded-full bg-white shadow transition-transform duration-200" :class="volume!.isRead ? 'translate-x-4' : 'translate-x-0.5'" />
      </div>
    </button>

    <!-- Delete cover button (only when cover exists) -->
    <button
      v-if="volume!.coverUrl"
      class="flex items-center gap-2 w-full px-3 py-2 rounded-xl border border-error/20 bg-error/5 hover:bg-error/10 hover:border-error/40 transition-all duration-150 text-xs font-medium text-error/50 hover:text-error/80"
      :class="{ 'opacity-50 pointer-events-none': deleteCoverMutation.isPending.value }"
      @click="deleteCoverMutation.mutate()"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
      </svg>
      Supprimer la couverture
    </button>
  </div>
</div>
```

Also update the cover ring color binding (currently uses `volumeStatus === 'owned'` etc.) — just uses the same `volumeStatus` computed which is now `'owned'|'wished'|'none'`, so no logic change needed there.

---

### Step 26 — Page: update MangaDetailPage.vue
**File:** `front/src/pages/MangaDetailPage.vue` *(modify)*
**Why:** All references to `isOwned`, `isWished`, and the old `toggleVolume`/`purchaseVolume` API need updating.

**Imports:** Replace `toggleVolume, purchaseVolume` with `setVolumeStatus, toggleVolumeRead`.

**Computed `missingVolumes`** — replace:
```typescript
const missingVolumes = computed(() => sortedVolumes.value.filter((v) => !v.isOwned && !v.isWished))
```
With:
```typescript
const missingVolumes = computed(() => sortedVolumes.value.filter((v) => v.status === 'none'))
```

**Mutation `toggleMutation`** — replace with two mutations:
```typescript
const setStatusMutation = useMutation({
  mutationFn: ({ veId, status }: { veId: string; status: VolumeEntryStatus }) =>
    setVolumeStatus(id, veId, status),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    closeContextMenu()
  },
})

const toggleReadMutation = useMutation({
  mutationFn: (veId: string) => toggleVolumeRead(id, veId),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    closeContextMenu()
  },
})
```

**Remove `purchaseMutation`** — its functionality is now `setStatusMutation.mutate({ veId, status: 'owned' })`.

**`volumeRingClass`** — replace:
```typescript
function volumeRingClass(ve: VolumeEntry): string {
  if (batchMode.value && selectedIds.value.has(ve.id)) return 'ring-primary'
  if (ve.status === 'owned' && ve.isRead) return 'ring-info/80'
  if (ve.status === 'owned') return 'ring-success/70'
  if (ve.status === 'wished') return 'ring-warning/60'
  if (ve.volumeStatus === 'announced') return 'ring-base-content/20 ring-dashed'
  return 'ring-base-300/30'
}
```

**`volumeOpacityClass`** — replace:
```typescript
function volumeOpacityClass(ve: VolumeEntry): string {
  if (ve.status === 'owned') return 'opacity-100'
  if (ve.status === 'wished') return 'opacity-65'
  if (ve.volumeStatus === 'announced') return 'opacity-30'
  return 'opacity-25 grayscale'
}
```

**`selectOwned`** — replace `v.isOwned` with `v.status === 'owned'`.

**`selectUnread`** — replace `v.isOwned && !v.isRead` with `v.status === 'owned' && !v.isRead`.

**Batch toggle function** — replace `batchToggle(field)` with two separate:
```typescript
async function batchSetStatus(status: VolumeEntryStatus) {
  if (selectedIds.value.size === 0) return
  const count = selectedIds.value.size
  const ids = [...selectedIds.value]
  isBatchProcessing.value = true
  try {
    await Promise.all(ids.map((veId) => setVolumeStatus(id, veId, status)))
    await qc.invalidateQueries({ queryKey: ['collection', id] })
    await qc.invalidateQueries({ queryKey: ['collection'] })
    await qc.invalidateQueries({ queryKey: ['wishlist'] })
    await qc.invalidateQueries({ queryKey: ['stats'] })
    selectedIds.value = new Set()
    ui.addToast(`${count} tome${count > 1 ? 's' : ''} mis à jour`, 'success')
  } finally {
    isBatchProcessing.value = false
  }
}

async function batchToggleRead() {
  if (selectedIds.value.size === 0) return
  const count = selectedIds.value.size
  const ids = [...selectedIds.value]
  isBatchProcessing.value = true
  try {
    await Promise.all(ids.map((veId) => toggleVolumeRead(id, veId)))
    await qc.invalidateQueries({ queryKey: ['collection', id] })
    await qc.invalidateQueries({ queryKey: ['stats'] })
    selectedIds.value = new Set()
    ui.addToast(`${count} tome${count > 1 ? 's' : ''} mis à jour`, 'success')
  } finally {
    isBatchProcessing.value = false
  }
}
```

**Template — volume grid card** for announced volumes, in the `v-else` (no coverUrl) block, add a clock icon instead of the book icon:
```html
<div v-else class="w-full h-full flex flex-col items-center justify-center bg-base-200">
  <!-- announced: clock icon -->
  <template v-if="ve.volumeStatus === 'announced'">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  </template>
  <template v-else>
    <span class="font-bold text-xl" :class="ve.status === 'owned' ? 'text-base-content/50' : ve.status === 'wished' ? 'text-warning/60' : 'text-base-content/15'">
      {{ ve.number }}
    </span>
  </template>
</div>
```

**Template — wished badge** — replace `ve.isWished && !ve.isOwned` with `ve.status === 'wished'`.

**Template — number label** — replace boolean references with status.

**Template — legend** (the `hidden sm:flex gap-3` block) — add "Annoncé":
```html
<span class="flex items-center gap-1.5">
  <span class="w-2 h-2 rounded-sm border border-dashed border-base-content/30 inline-block" />Annoncé
</span>
```

**Template — context menu** — replace all `isOwned`/`isWished` checks with `status` comparisons. Replace `purchaseMutation.mutate(contextMenu.ve.id)` with `setStatusMutation.mutate({ veId: contextMenu.ve.id, status: 'owned' })`. Update menu items accordingly — no "Marquer acheté", use "Marquer possédé" for the WISHED→OWNED transition.

**Template — batch action bar** — update `batchToggle('isOwned')` → `batchSetStatus('owned')`, `batchToggle('isWished')` → `batchSetStatus('wished')`, `batchToggle('isRead')` → `batchToggleRead()`. Update `v-if` conditions to use `status`.

---

### Step 27 — Page: update WishlistPage.vue
**File:** `front/src/pages/WishlistPage.vue` *(modify)*
**Why:** Replace `purchaseVolume` import with `setVolumeStatus`; update "acheté" wording; update wishedVolumes filter.

Replace import:
```typescript
import { getWishlist, clearWishlist, purchaseVolume } from '@/api/wishlist'
```
With:
```typescript
import { getWishlist, clearWishlist } from '@/api/wishlist'
import { setVolumeStatus } from '@/api/collection'
```

Update `wishedVolumes`:
```typescript
function wishedVolumes(entry: WishlistEntry): VolumeEntry[] {
  return entry.volumes.filter((v) => v.status === 'wished')
}
```

Update `batchPurchase` — replace `purchaseVolume(entryId, veId)` with `setVolumeStatus(entryId, veId, 'owned')`. Update toast:
```typescript
ui.addToast(`${count} tome${count > 1 ? 's' : ''} marqué${count > 1 ? 's' : ''} comme possédé${count > 1 ? 's' : ''}`, 'success')
```

Update `purchaseMutation` — replace `purchaseVolume` call with `setVolumeStatus(entryId, veId, 'owned')`. Update toast to use `t('wishlist.purchased')` (will be updated in i18n step).

Update the hint text in template:
```html
{{ batchMode ? 'Appuyez sur les tomes pour les sélectionner' : 'Appuyez sur un tome pour le marquer comme possédé' }}
```

Update batch action bar button label — replace "Marquer acheté" with "Marquer possédés".

Update volume chip title attribute: `Tome ${ve.number} — Marquer possédé`.

---

## i18n Keys

**`front/src/i18n/fr.json`** — modify:
```json
{
  "wishlist": {
    "purchased": "Tome ajouté à la collection",
    "purchase": "Possédé"
  },
  "volume": {
    "price": "Prix (€)",
    "announced": "Annoncé",
    "owned": "Possédé",
    "wished": "Souhaité",
    "none": "Non suivi",
    "deleteCover": "Supprimer la couverture"
  }
}
```

**`front/src/i18n/en.json`** — modify:
```json
{
  "wishlist": {
    "purchased": "Volume added to collection",
    "purchase": "Owned"
  },
  "volume": {
    "price": "Price (€)",
    "announced": "Announced",
    "owned": "Owned",
    "wished": "Wished",
    "none": "Not tracked",
    "deleteCover": "Delete cover"
  }
}
```

---

## QA Gates

Run every command below in order. **Do not skip any gate.** If a gate fails, fix it before moving to the next one.

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
Expected: exit code 0 after fix

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
Expected: no errors or warnings.

### 7. Frontend Unit Tests
```bash
docker compose exec app npx vitest run
```
Expected: all tests pass.

### 8. Smoke Test (manual)
```
1. docker compose up -d
2. Open http://localhost:5173 — navigate to a manga detail
3. Verify: volume grid shows announced volumes with dashed ring + clock icon
4. Click an announced volume → modal opens → "Annoncé" badge visible, "Marquer possédé" button absent or disabled
5. Click a released, none-status volume → modal shows "Souhaiter" button
6. Click "Souhaiter" → ring turns warning, number label changes color
7. Click the same volume again → modal shows "Marquer possédé" + "Retirer des souhaits"
8. Click "Marquer possédé" → ring turns success
9. Open wishlist page → volume appears, clicking marks it as possédé (no "acheté" anywhere)
10. Back in modal: volume is Owned → "Lu / Non lu" toggle visible, "Supprimer la couverture" visible if cover exists
11. Dashboard stats: owned/wished counts match expected
```

---

## Execution Checklist

### Backend
- [ ] Step 1 — Create VolumeStatusEnum
- [ ] Step 2 — Create VolumeEntryStatusEnum
- [ ] Step 3 — Update Volume entity (add status field + toArray)
- [ ] Step 4 — Update VolumeEntry entity (replace isOwned/isWished with status enum)
- [ ] Step 5 — Update CollectionEntry toArray (ownedCount/wishedCount/ownedValue)
- [ ] Step 6 — Create SetVolumeStatusCommand
- [ ] Step 7 — Create SetVolumeStatusHandler (with Announced→Owned guard)
- [ ] Step 8 — Create ToggleVolumeReadCommand
- [ ] Step 9 — Create ToggleVolumeReadHandler
- [ ] Step 10 — Create SetVolumeStatusRequest
- [ ] Step 11 — Update CollectionController (new routes, remove old)
- [ ] Step 12 — Delete ToggleVolume + PurchaseVolume CQRS files
- [ ] Step 13 — Update AddRemainingToWishlistHandler
- [ ] Step 14 — Update ClearWishlistHandler
- [ ] Step 15 — Update GetStatsHandler (DQL enum queries)
- [ ] Step 16 — Create DeleteVolumeCoverCommand
- [ ] Step 17 — Create DeleteVolumeCoverHandler
- [ ] Step 18 — Extend UpdateVolumeCommand with status
- [ ] Step 19 — Update UpdateVolumeHandler to apply status
- [ ] Step 20 — Update UpdateVolumeRequest to accept status
- [ ] Step 21 — Update MangaController (wire status + add DELETE cover route)

### Database
- [ ] Migration generated via doctrine:migrations:diff
- [ ] Data migration SQL manually inserted (UPDATE owned/wished rows)
- [ ] Migration applied

### Frontend
- [ ] Step 22 — types/index.ts updated (VolumeStatus, VolumeEntryStatus, VolumeEntry)
- [ ] Step 23 — api/collection.ts updated (setVolumeStatus, toggleVolumeRead)
- [ ] Step 24 — api/manga.ts updated (deleteVolumeCover, updateVolume status)
- [ ] Step 25 — EnrichVolumeModal.vue (new mutations, pill buttons, announced badge, delete cover)
- [ ] Step 26 — MangaDetailPage.vue (status refs, ring/opacity, batch, legend, context menu)
- [ ] Step 27 — WishlistPage.vue (no acheté, setVolumeStatus, wishedVolumes filter)
- [ ] i18n keys added to fr.json and en.json

### QA
- [ ] PHPStan passes
- [ ] CS Fixer passes
- [ ] PHPUnit passes
- [ ] Doctrine migrations status clean
- [ ] TypeScript noEmit passes
- [ ] ESLint passes
- [ ] Vitest passes
- [ ] Smoke test done

### Git
- [ ] All changes on feature branch
- [ ] Single commit (amend if needed: `git commit --amend`)
- [ ] PR created
