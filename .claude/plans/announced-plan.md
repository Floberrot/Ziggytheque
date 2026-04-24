# Plan: Announced Volumes + Toggle isOwned

## Architecture decision: `isAnnounced` lives on `VolumeEntry`

Same layer as `isOwned` / `isRead` / `isWished` — it is user collection state, not book metadata.
This means the existing `PATCH /api/collection/:id/volumes/:veId/toggle` endpoint handles it with
a minimal addition; no new endpoint or repository needed.

Business rules:
- `isOwned → true` clears `isWished` (existing rule, unchanged)
- `isOwned → true` clears `isAnnounced` (new rule — owning a volume makes announced irrelevant)
- `isAnnounced` is only meaningful when `!isOwned`
- All four fields are independently reversible from the UI (within the constraints above)

---

## Phase 1 — Backend: Entity + Migration

### `back/src/Collection/Domain/VolumeEntry.php`
- Add `#[Column(type: 'boolean', options: ['default' => false])] private bool $isAnnounced = false;`
- Add `public function toggleAnnounced(): void { $this->isAnnounced = !$this->isAnnounced; }` following the pattern of existing toggles
- Add `'isAnnounced' => $this->isAnnounced` to `toArray()`

### New migration `back/migrations/Version20260424000000.php`
```sql
ALTER TABLE volume_entries ADD COLUMN is_announced BOOLEAN NOT NULL DEFAULT FALSE;
```

---

## Phase 2 — Backend: Extend Toggle Endpoint

### `back/src/Collection/Infrastructure/Http/ToggleVolumeRequest.php`
- Add `'isAnnounced'` to `#[Assert\Choice(choices: [...])]`:
```php
#[Assert\Choice(choices: ['isOwned', 'isRead', 'isWished', 'isAnnounced'])]
```

### `back/src/Collection/Application/ToggleVolume/ToggleVolumeHandler.php`
- When toggling `isOwned → true`: clear both `isWished` and `isAnnounced` (extend existing rule)
- Verify the existing toggle dispatch handles the new field correctly (likely uses
  `$volumeEntry->{'toggle'.ucfirst($command->field)}()` or equivalent)

---

## Phase 3 — Backend: Tests

### `back/tests/Unit/Collection/Application/ToggleVolumeHandlerTest.php` (create or extend)

Unit test cases using in-memory fakes:

| Test | Assert |
|---|---|
| `testToggleIsAnnouncedToTrue` | `isAnnounced=true` |
| `testToggleIsAnnouncedToFalse` | `isAnnounced=false` |
| `testToggleIsOwnedToFalse` | `isOwned=false` (regression — reverse direction) |
| `testToggleIsOwnedClearsAnnounced` | `isOwned=true` → `isAnnounced=false`, `isWished=false` |

### `back/tests/Functional/Collection/CollectionControllerTest.php` (create or extend)

Full E2E WebTestCase flow — each test authenticates with the JWT gate, then:

| Request | Expect |
|---|---|
| `PATCH toggle {field: isAnnounced}` on entry with `isAnnounced=false` | 204, `GET detail` shows `isAnnounced=true` |
| `PATCH toggle {field: isAnnounced}` again | 204, `isAnnounced=false` |
| `PATCH toggle {field: isOwned}` on entry with `isOwned=true` | 204, `isOwned=false` |
| `PATCH toggle {field: isOwned}` on entry with `isAnnounced=true` | 204, `isOwned=true`, `isAnnounced=false` |
| `PATCH toggle {field: invalid}` | 422 Unprocessable |
| `PATCH toggle` on unknown `volumeEntryId` | 404 |

Use `WebTestCase` with `dama/doctrine-test-bundle` for DB isolation per test.
Factories/fixtures build Manga → Volume → CollectionEntry → VolumeEntry graph in setUp.
Verify state by calling `GET /api/collection/{id}` and inspecting the volumes array.

---

## Phase 4 — Frontend: Install Lucide + Types + API

### Install `lucide-vue-next`
```bash
npm install lucide-vue-next
```
Individual tree-shaken imports everywhere: `import { Megaphone, Package, ... } from 'lucide-vue-next'`

### `front/src/types/index.ts`
- Add `isAnnounced: boolean` to `VolumeEntry` interface
- Update `VolumeToggleField`:
```typescript
export type VolumeToggleField = 'isOwned' | 'isRead' | 'isWished' | 'isAnnounced'
```

### `front/src/api/collection.ts`
No change needed — `toggleVolume()` already accepts `VolumeToggleField`.

---

## Phase 4b — Frontend: Migrate all inline SVGs to Lucide

Replace every inline `<svg>` in the project with the corresponding `lucide-vue-next` component.
All existing icons use `fill="none" stroke="currentColor"` — Lucide matches this style exactly.
Size via `class="h-4 w-4"` etc. stays identical.

### Icon mapping

| Current usage | Lucide component | Files |
|---|---|---|
| Back arrow | `<ArrowLeft />` | `MangaDetailPage.vue`, `AddMangaPage.vue` |
| Arrow right | `<ArrowRight />` | `WishlistPage.vue` |
| Search | `<Search />` | `AddMangaPage.vue`, `CollectionPage.vue`, `EnrichVolumeModal.vue` |
| Refresh/sync | `<RefreshCw />` | `AddMangaPage.vue`, `MangaDetailPage.vue`, `EnrichVolumeModal.vue` |
| Book (placeholder/nav) | `<Book />` | `WishlistPage.vue`, `AddMangaPage.vue`, `CollectionPage.vue`, `MangaDetailPage.vue`, `EnrichVolumeModal.vue`, `MangaCard.vue` |
| BookOpen (read status) | `<BookOpen />` | `MangaDetailPage.vue` |
| Image placeholder | `<ImageOff />` | `AddMangaPage.vue`, `EnrichVolumeModal.vue` |
| Plus | `<Plus />` | `CollectionPage.vue`, `WishlistPage.vue` |
| PlusCircle (nav Add) | `<PlusCircle />` | `MainLayout.vue` |
| Check (confirm/owned) | `<Check />` | `AddMangaPage.vue`, `MangaDetailPage.vue`, `EnrichVolumeModal.vue` |
| CheckSquare (batch select) | `<CheckSquare />` | `MangaDetailPage.vue`, `WishlistPage.vue` |
| X (close/cancel) | `<X />` | `AddMangaPage.vue`, `MangaDetailPage.vue`, `WishlistPage.vue`, `EnrichVolumeModal.vue` |
| Star (wishlist) | `<Star />` | `WishlistPage.vue`, `AddMangaPage.vue`, `MangaDetailPage.vue`, `EnrichVolumeModal.vue` |
| Pencil/edit | `<Pencil />` | `MangaDetailPage.vue` |
| Trash/delete | `<Trash2 />` | `MangaDetailPage.vue`, `EnrichVolumeModal.vue` |
| Eye (preview) | `<Eye />` | `MangaDetailPage.vue` |
| Shopping cart | `<ShoppingCart />` | `WishlistPage.vue`, `MangaDetailPage.vue`, `EnrichVolumeModal.vue` |
| Tag/label | `<Tag />` | `MangaDetailPage.vue` |
| Heart (rating) | `<Heart />` | `BaseHeartRating.vue`, `StatCard.vue` |
| Hamburger menu | `<Menu />` | `MainLayout.vue` |
| Settings/gear | `<Settings />` | `MainLayout.vue` |
| Logout/exit | `<LogOut />` | `MainLayout.vue` |
| Globe/language | `<Globe />` | `MainLayout.vue` |
| Bell (notifications nav) | `<Bell />` | `MainLayout.vue` |
| Palette/theme | `<Palette />` | `BaseThemeSwitch.vue`, `MainLayout.vue` |
| Bar chart (dashboard nav) | `<LayoutDashboard />` | `MainLayout.vue` |
| Library (collection nav) | `<Library />` | `MainLayout.vue` |
| Layers (owned volumes stat) | `<Layers />` | `StatCard.vue` |

### `DashboardPage.vue`
Replace `📚` emoji (line 131) with `<Book class="h-8 w-8" />`.

### Half-heart in `BaseHeartRating.vue`
Lucide has no half-heart. Keep the existing inline SVG path for the half-heart only; replace the full and empty hearts with `<Heart />`.

---

## Phase 5 — Frontend: Context Menu (`MangaDetailPage.vue`)

Updated context menu — each entry: **SVG icon (lucide-vue-next) + one word**, no full sentences.
"Marquer acheté" removed: toggling isOwned=true IS the purchase action.

```
────── Tome {number} ──────
[!isOwned]               <Megaphone />  Annoncé    → toggle isAnnounced  (active: filled style)
[!isOwned]               <Package />   Possédé    → toggle isOwned
[isOwned]                <Package />   Possédé    → toggle isOwned       (active: filled style)
[isOwned]                <BookOpen />  Lu         → toggle isRead        (active: filled style)
[!isOwned && !isWished]  <Star />      Wishlist   → toggle isWished
[isWished && !isOwned]   <Star />      Wishlist   → toggle isWished      (active: filled style)
──────────────────────────────
<Info />  Détails                                  → openVolumeModal
```

Note: `purchaseVolume` endpoint is no longer called from this menu.
Owning a volume implicitly means purchased — no separate "acheter" step.

---

## Phase 6 — Frontend: `EnrichVolumeModal.vue` — Button Harmonization

Replace the current ad-hoc button layout with a consistent 4-button row.
Pattern: **`lucide-vue-next` icon + one word**, active = filled, inactive = outline variant.
No long phrases — single word labels only.

| Button | Lucide icon | Word | Condition | DaisyUI class (active / inactive) | Action |
|---|---|---|---|---|---|
| Announced | `<Megaphone />` | "Annoncé" | `!isOwned` only | `btn-neutral` / `btn-neutral btn-outline` | toggle `isAnnounced` |
| Owned | `<Package />` | "Possédé" | always | `btn-success` / `btn-success btn-outline` | toggle `isOwned` |
| Wishlist | `<Star />` | "Wishlist" | `!isOwned` only | `btn-warning` / `btn-warning btn-outline` | toggle `isWished` |
| Read | `<BookOpen />` | "Lu" | `isOwned` only | `btn-info` / `btn-info btn-outline` | toggle `isRead` |

All four toggle in both directions — no "Marquer acheté" button anywhere.

### Status badge (computed, in modal header — mutually exclusive priority):

| State | Badge class | Label |
|---|---|---|
| `isAnnounced && !isOwned` | `badge-neutral` | "Annoncé" |
| `isOwned` | `badge-success` | "Possédé" |
| `isWished && !isOwned` | `badge-warning` | "Souhaité" |
| none | `badge-ghost` | "Non suivi" |

### Announced volume — cover placeholder
When `isAnnounced=true && !coverUrl`:
- Replace empty cover area with a styled `div` using `bg-base-300` + diagonal-stripe CSS pattern
- Overlay a centered `badge badge-neutral` "Annoncé"
- Keep the cover search UI below — so the user can attach a cover once released

---

## Phase 7 — Frontend: Volume Card Visual (`MangaDetailPage.vue` grid)

Ring color for announced state (replaces/extends existing ring logic):

```
isAnnounced && !isOwned   → ring-2 ring-base-content ring-dashed opacity-60
isOwned                   → ring-2 ring-success (existing, isAnnounced always false here)
isWished && !isOwned      → ring-2 ring-warning (existing)
default                   → ring-1 ring-base-300 opacity-40
```

---

## Scope boundary

- No change to `Manga`, `Volume`, `Wishlist`, `Stats` bounded contexts
- No new API endpoint — the existing toggle endpoint absorbs the new field
- `MangaCard.vue` (collection list view): skip unless `announcedCount` is added to `toDetailArray()`
- i18n keys added to `fr.json` + `en.json` for all new labels

---

## Execution order

1. Migration + `VolumeEntry` entity change (unblocks everything)
2. `ToggleVolumeRequest` — add `isAnnounced` to allowed choices
3. `ToggleVolumeHandler` — verify dispatch handles new field
4. PHPUnit unit tests → functional/E2E tests
5. `npm install lucide-vue-next` + frontend types
6. Migrate all inline SVGs to Lucide (Phase 4b) — bulk replacement, no behavior change
7. Context menu updates (Phase 5) — uses Lucide icons already installed
8. `EnrichVolumeModal` button harmonization + status badge (Phase 6)
9. Volume card ring class + announced cover placeholder (Phase 7)
