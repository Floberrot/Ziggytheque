# Frontend Architecture Rework — Ziggytheque

## Context

`front/src/` has drifted far from its stated Atomic Design contract: 4 atoms total, 1 molecule, 3 "organisms" (one of which is actually a layout at 241 LOC), and pages that have absorbed every missing primitive. MangaDetailPage is 942 LOC, AddMangaPage is 576, WishlistPage is 397, EnrichVolumeModal is 456 — each reimplements buttons, inputs, modals, chips, empty states, progress bars, context menus, and selection logic inline.

Visually the app runs on vanilla DaisyUI with all 32 themes enabled, system font stack, and no custom design tokens — a safe, generic look with no brand personality for what is essentially a collector's vault for manga.

**Goals of this rework:**
1. Rigorously apply Atomic Design so **no page exceeds 200 LOC** and every repeating UI pattern is promoted to a reusable atom/molecule/organism.
2. Move from "classic DaisyUI" to a **modern, distinctive "Editorial Dark + neon completion glows"** visual identity — covers as the hero, serif display over deep ink, rewards for collection milestones — while keeping accessibility strong (Headless UI, focus-visible, `prefers-reduced-motion`, landmarks, ARIA).
3. Encode the architecture as **hard rules** in `.claude/CLAUDE.md` and a new `.claude/skills/front-atomic-design/` skill so future work cannot regress.

User-confirmed decisions:
- **Theme**: single `ziggy-light` + `ziggy-dark` (with `--prefersdark`); switcher reduces to light/dark/system; drop the 32-theme picker.
- **Visual direction**: Editorial Dark with neon completion glows (Fraunces display + Inter UI; vermilion / aged-gold / jade on deep ink).
- **PR scope**: single mega-PR, one commit (per project rule). Work lands in phased order internally but ships together.

---

## Target folder structure

```
front/src/
├── main.ts                               # register persistedstate, motion, i18n
├── App.vue                               # dynamic <component :is="layout">
├── router/{index,guards}.ts              # meta.layout + meta.requiresAuth
├── design/
│   ├── tokens.css                        # :root + [data-theme="ziggy-dark"] custom props
│   ├── daisy.css                         # @plugin "daisyui" { themes: ziggy-light --default, ziggy-dark --prefersdark }
│   ├── typography.css                    # @font-face + type scale
│   └── motion.css                        # --motion-* + prefers-reduced-motion
├── assets/main.css                       # @import "tailwindcss" + design/*
├── layouts/
│   ├── AppLayout.vue                     # authed shell (sidebar + bottom nav + outlet + transition)
│   └── AuthLayout.vue                    # centered card for gate
├── components/
│   ├── atoms/                            # ~20 A* primitives, no store/query access
│   ├── molecules/                        # ~15 M* compositions of atoms
│   └── organisms/                        # cross-domain O* (sidebar, bottom nav, toast host, batch bar)
├── features/
│   ├── collection/{organisms,composables,pages}/
│   ├── wishlist/{organisms,pages}/
│   ├── add/{organisms,pages}/
│   ├── dashboard/{organisms,pages}/
│   ├── notifications/{organisms,pages}/
│   └── gate/pages/
├── composables/
│   ├── queries/                          # ONLY place useQuery/useMutation live
│   └── ui/                               # useConfirm, useToast, useBreakpoint, useBatchSelection, useContextMenu, useKeyboardShortcut, useCoverUrl
├── stores/                               # useAuthStore, useThemeStore (light|dark|system), useUiStore
├── api/                                  # unchanged; dedupe purchaseVolume (keep collection.ts, drop from wishlist.ts)
├── i18n/ · types/ · utils/
```

Domain-agnostic primitives live under `components/`; domain-coupled organisms live under `features/<domain>/organisms/` to keep `components/organisms/` small.

---

## Design system (phase 0)

### Dependencies to install

```
@headlessui/vue @floating-ui/vue
@vueuse/core @vueuse/motion
@iconify/vue @iconify-json/lucide
@fontsource-variable/inter @fontsource-variable/fraunces
pinia-plugin-persistedstate
@testing-library/vue @axe-core/playwright axe-core
```

### Tokens (`design/tokens.css`)

```css
:root {
  /* radii */
  --radius-xs: 4px;   --radius-sm: 8px;  --radius-md: 12px;
  --radius-lg: 16px;  --radius-xl: 22px; --radius-2xl: 28px;
  --radius-full: 9999px;

  /* elevation */
  --shadow-xs: 0 1px 2px 0 rgb(24 16 34 / 0.06);
  --shadow-sm: 0 2px 8px -2px rgb(24 16 34 / 0.10);
  --shadow-md: 0 8px 24px -8px rgb(24 16 34 / 0.18);
  --shadow-lg: 0 20px 48px -16px rgb(24 16 34 / 0.28);
  --shadow-xl: 0 32px 80px -24px rgb(24 16 34 / 0.38);
  --shadow-glow-primary: 0 0 24px -4px oklch(0.72 0.18 12 / 0.55);
  --shadow-glow-success: 0 0 24px -4px oklch(0.74 0.15 160 / 0.55);

  /* motion */
  --motion-ease-out: cubic-bezier(0.22, 1, 0.36, 1);
  --motion-ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
  --motion-ease-standard: cubic-bezier(0.4, 0, 0.2, 1);
  --motion-fast: 150ms; --motion-base: 220ms; --motion-slow: 360ms;

  /* fluid type */
  --fs-xs: clamp(0.72rem, 0.70rem + 0.1vw, 0.78rem);
  --fs-sm: clamp(0.82rem, 0.80rem + 0.1vw, 0.88rem);
  --fs-base: clamp(0.95rem, 0.92rem + 0.15vw, 1rem);
  --fs-lg: clamp(1.10rem, 1.05rem + 0.25vw, 1.22rem);
  --fs-xl: clamp(1.35rem, 1.25rem + 0.5vw, 1.55rem);
  --fs-2xl: clamp(1.75rem, 1.55rem + 1vw, 2.1rem);
  --fs-3xl: clamp(2.2rem, 1.85rem + 1.8vw, 2.9rem);
  --fs-display: clamp(2.8rem, 2.2rem + 3vw, 3.8rem);
}

@media (prefers-reduced-motion: reduce) {
  :root { --motion-fast: 0ms; --motion-base: 0ms; --motion-slow: 0ms; }
  * { animation-duration: 0.001ms !important; transition-duration: 0.001ms !important; }
}
```

### Themes (`design/daisy.css`)

```css
@plugin "daisyui" {
  themes: ziggy-light --default, ziggy-dark --prefersdark;
}

@plugin "daisyui/theme" {
  name: "ziggy-dark"; color-scheme: dark; default: false; prefersdark: true;
  --color-base-100:    oklch(17% 0.02 280);   /* #1A1822 deep ink */
  --color-base-200:    oklch(22% 0.02 280);   /* panel */
  --color-base-300:    oklch(28% 0.025 280);  /* border */
  --color-base-content:oklch(96% 0.01 280);   /* paper */
  --color-primary:     oklch(72% 0.18 12);    /* vermilion red */
  --color-primary-content: oklch(18% 0.04 12);
  --color-secondary:   oklch(78% 0.14 75);    /* aged gold */
  --color-accent:      oklch(72% 0.15 200);   /* indigo-cyan */
  --color-success:     oklch(74% 0.15 160);   /* jade = "consumed" */
  --color-warning:     oklch(80% 0.14 75);
  --color-error:       oklch(66% 0.21 22);
  --color-info:        oklch(72% 0.13 240);
  --radius-selector: 12px; --radius-field: 10px; --radius-box: 22px;
}

@plugin "daisyui/theme" {
  name: "ziggy-light"; color-scheme: light; default: true;
  --color-base-100: oklch(98% 0.005 280);
  --color-base-200: oklch(94% 0.008 280);
  --color-base-300: oklch(88% 0.01 280);
  --color-base-content: oklch(20% 0.02 280);
  --color-primary: oklch(54% 0.22 12);
  /* ...mirror semantic roles */
}
```

### Typography

`Fraunces` variable (display, with `font-optical-sizing: auto; font-variation-settings: "SOFT" 40, "WONK" 1;`) + `Inter` variable (UI). Numeric uses `font-variant-numeric: tabular-nums`. Loaded via `@fontsource-variable/*` — zero layout shift, self-hosted.

### Theme store simplification

`useThemeStore` state becomes `'light' | 'dark' | 'system'`. `system` sets no `data-theme` attribute so DaisyUI's `--prefersdark` kicks in via media query. Persisted via `pinia-plugin-persistedstate`. `BaseThemeSwitch` becomes a 3-way `MSegmentedControl`.

---

## Atom inventory (~20, `components/atoms/`)

All prefixed `A`, purely presentational (props in / events out, no stores/queries).

| Atom | Purpose |
|---|---|
| `AButton` | All buttons. Variants: `primary`, `ghost`, `danger`, `success`, `warning`, `outline`. Sizes `sm/md/lg`. Icon slot + `loading`. |
| `AIconButton` | Icon-only; required `aria-label`. |
| `AIcon` | `@iconify/vue` wrapper with typed `name` (Lucide family). |
| `AInput` | Text/number/url/search; label + error + `aria-describedby`. |
| `ATextarea` | Auto-grow. |
| `ASelect` | Native styled; swap to Headless UI Listbox for renderers. |
| `AFormField` | Label + hint + error + default slot wrapper. |
| `ABadge` | `variant` + `subtle` flag; replaces every inline `badge badge-*`. |
| `AChip` | Pill with dot indicator (status, counts). |
| `ATag` | Neutral label (genre, language, edition). |
| `AKbd` | Keyboard shortcut hint. |
| `ASpinner` | Sized spinner. |
| `ASkeleton` | Shimmer with `aspect`, `radius`, `width`. |
| `AProgressBar` | Multi-segment (read/owned/wished). |
| `AProgressRing` | Circular; dashboard KPIs + milestone glow. |
| `ADivider` | Horizontal/vertical/label. |
| `AEmptyState` | Icon + title + description + CTA slot. |
| `AAvatar` | Initials + optional image. |
| `ASwitch` | Headless UI `Switch` wrapper. |
| `AHeartRating` | Current `BaseHeartRating` renamed. |
| `ATooltip` | Floating UI wrapper. |
| `AFade` / `ASlideUp` | `<Transition>` primitives with motion tokens. |

---

## Molecule inventory (~15, `components/molecules/`)

| Molecule | Purpose |
|---|---|
| `MSearchInput` | `AInput` + icon + clear + loading. |
| `MAutocomplete` | Headless UI `Combobox` + filtered list (French editions). |
| `MConfirmDialog` | Headless UI `Dialog` + title/description/confirm/cancel. |
| `MModal` | Headless UI `Dialog` shell; header/body/footer slots; size variants. |
| `MBottomSheet` | Mobile-first `Dialog` variant. |
| `MToast` | `role="status" aria-live="polite"` (assertive for errors). |
| `MContextMenu` | Headless UI `Menu` + Floating UI positioning. |
| `MSegmentedControl` | Headless UI `RadioGroup` pill (status, theme switch). |
| `MStepper` | Numbered step indicator (AddMangaPage). |
| `MPagination` | Numbered pager with `aria-current="page"`. |
| `MStatCard` | Title + big value + trend/icon. |
| `MStatPill` | Colored dot + value + label. |
| `MCoverImage` | `<img>` + fallback + `aspect-[2/3]` + opacity/ring modifiers. |
| `MCardTile` | Generic cover tile (selection checkmark, hover ring, badge slot). |
| `MInlineEdit` | Popover + input + save/cancel. |
| `MNotificationItem` | Icon + message + mark-read. |

---

## Organism inventory

**Cross-domain** (`components/organisms/`):

- `OAppSidebar` — desktop nav rail
- `OBottomNav` — mobile floating pill
- `OSettingsSheet` — bottom sheet (theme + locale + logout)
- `OToastHost` — toast container
- `OBatchActionBar` — fixed-bottom pluralized action bar (reused by detail + wishlist)

**Domain-specific** (`features/<domain>/organisms/`):

- **collection**: `MangaCoverCard`, `CollectionFilterBar`, `CollectionStatsStrip`, `MangaHero`, `VolumeMatrix`, `VolumeTile`, `BatchPricePill`, `SyncVolumesPanel`
- **wishlist**: `WishlistRow`, `WishlistBatchBar`
- **add**: `ExternalSearchResults`, `MangaForm`, `DestinationChooser`, `EnrichPanel`, `VolumeActionsPanel`
- **dashboard**: `StatSummary`, `ValueBreakdown`, `GenreBreakdownChart`, `RecentAdditionsList`
- **notifications**: `NotificationList`
- **gate**: `GateForm`

---

## Composables reorganization

**Amended rule**: `useQuery`/`useMutation`/`useInfiniteQuery` may ONLY be imported inside `composables/queries/**`. Pages and organisms consume those composables.

### `composables/queries/`

- `useCollectionQueries.ts` — `useCollectionList`, `useCollectionEntry(id)`, `useAddToCollection`, `useRemoveFromCollection`, `useUpdateReadingStatus`, `useToggleVolume`, `useBatchToggleVolumes`, `useAddRemainingToWishlist`, `usePurchaseVolume`, `useSyncVolumes`, `useBatchSetPrice`, `useUpdateRating`. Each mutation owns its invalidation list.
- `useMangaQueries.ts` — `useImportManga`, `useUpdateManga`, `useUpdateVolume`, `useInfiniteExternalSearch` (replaces `useExternalSearch` with `useInfiniteQuery`).
- `useWishlistQueries.ts` — `useWishlistList`, `useClearWishlist`, `useBatchPurchase`.
- `useStatsQueries.ts` — `useStats`.
- `useNotificationQueries.ts` — `useNotifications({ refetchInterval: 60_000 })`, `useMarkNotificationRead`.
- `useAuthFlow.ts` — `useGate`, `useLogout`.

### `composables/ui/`

- `useConfirm` — imperative `confirm({title, description})` → Promise, drives a single app-level `MConfirmDialog`.
- `useToast` — typed wrapper over `useUiStore` (`success/error/info/warning`).
- `useBreakpoint` — `@vueuse/core` pre-configured.
- `useKeyboardShortcut` — `useMagicKeys` wrapper (Esc, Cmd+K).
- `useCoverUrl` — reactive cover URL helper.
- `useContextMenu` — `MContextMenu` at event position via Floating UI.
- `useBatchSelection` — generic selection set (`toggle/selectAll/clear/isSelected/items`).

---

## Page decomposition (targets)

| Page | Current LOC | Target LOC | Extracted into |
|---|---:|---:|---|
| `features/collection/pages/MangaDetailPage.vue` | 942 | ~140 | `MangaHero`, `VolumeMatrix`, `VolumeTile`, `EnrichPanel` (in `MModal`), `VolumeActionsPanel`, `OBatchActionBar`, `BatchPricePill`, `SyncVolumesPanel`, `useContextMenu`, `useConfirm`, `useBatchSelection`, `useCollectionQueries` |
| `features/add/pages/AddMangaPage.vue` | 576 | ~120 | `MStepper`, `ExternalSearchResults`, `MangaForm`, `DestinationChooser`, `MAutocomplete`, `useMangaQueries.useInfiniteExternalSearch` |
| `features/wishlist/pages/WishlistPage.vue` | 397 | ~90 | `WishlistRow`, `WishlistBatchBar`, `useBatchSelection`, `AEmptyState`, `ASkeleton` |
| `layouts/AppLayout.vue` (ex-MainLayout) | 241 | ~60 | `OAppSidebar`, `OBottomNav`, `OSettingsSheet`, `OToastHost` |
| `features/collection/pages/CollectionPage.vue` | 177 | ~110 | `CollectionStatsStrip`, `CollectionFilterBar`, `MangaCoverCard`, `MPagination`, `AEmptyState`, `ASkeleton` |
| `features/dashboard/pages/DashboardPage.vue` | 98 | ~60 | `StatSummary`, `ValueBreakdown`, `GenreBreakdownChart`, `RecentAdditionsList` |
| `features/notifications/pages/NotificationsPage.vue` | 46 | ~25 | `NotificationList`, `AEmptyState` |
| `features/gate/pages/GatePage.vue` | 66 | ~20 | `GateForm`, `AuthLayout` |

---

## Accessibility primitives

- **Headless UI** gives a11y-correct `Dialog` (focus trap, Esc, backdrop, `aria-modal`, return focus), `Menu` (roving tabindex, Esc, type-ahead), `Combobox` (`aria-autocomplete`, `aria-activedescendant`), `RadioGroup`, `Switch`, `Disclosure`. Replaces every home-rolled modal/menu/popover.
- **Focus ring**: global `:focus-visible { outline: 2px solid oklch(72% 0.18 12); outline-offset: 3px; border-radius: inherit; }` — vermilion ring beats any gradient background. `.focus-ring-inset` variant where outline would clip.
- **`prefers-color-scheme`**: honored natively via DaisyUI `--prefersdark`; `system` theme option sets no override.
- **`prefers-reduced-motion`**: encoded in `motion.css`; `@vueuse/motion` honors it automatically.
- **Landmarks**: `AppLayout` emits `<header>`, `<nav aria-label="Primary">`, `<main id="main">`. Skip link `<a href="#main">` first in tab order.
- **`MPagination`**: `aria-current="page"` + `aria-label="Page N sur M"`.
- **`VolumeMatrix`**: `role="listbox" aria-multiselectable="true"` in batch mode; tiles `role="option" aria-selected`.
- **Toast**: `role="status" aria-live="polite"`; errors `role="alert" aria-live="assertive"`.

---

## Motion layer

- Page transition in `AppLayout` (fade + 4px slide-up, `--motion-base`).
- Stagger via `v-motion` on `VolumeMatrix` tiles, `RecentAdditionsList`, `CollectionPage` grid (100% first hit only, cached after).
- 100% owned ring → `shadow-glow-primary` pulse (`@keyframes` respecting reduced-motion).
- 100% read → small gold `lucide:sparkles` corner icon (no animation unless motion allowed).
- Modal entrance: scale-up + fade via Headless UI `TransitionChild`.
- No page-wide parallax or scroll-hijack (accessibility-first).

---

## Enforcement (docs + skill updates)

This is an explicit deliverable — last step of the PR.

### Update `.claude/CLAUDE.md` (project-level)

Replace the current "Frontend (front/src/)" section with a richer one, and add a new **"Front Architecture — Hard Rules"** section:

1. **Pages are composition-only.** Files under `pages/` or `features/*/pages/` MUST NOT contain raw `<button>`, `<input>`, `<select>`, `<textarea>`, inline `<svg>`, or DaisyUI component classes (`btn-*`, `badge-*`, `alert-*`, `card-*`, `stat-*`). Use atoms/molecules.
2. **Data fetching is centralized.** `useQuery` / `useMutation` / `useInfiniteQuery` may be imported ONLY inside `composables/queries/**/*.ts`. Pages and organisms consume those composables.
3. **LOC budgets (hard caps).** Pages: 200 (target 150). Organisms: 300. Molecules: 150. Atoms: 100.
4. **Single theme.** Only `ziggy-light` and `ziggy-dark` are registered. Adding a theme requires architecture review.
5. **No inline SVG.** Use `<AIcon name="lucide:…" />`. Exceptions: `AIcon.vue`, `AHeartRating.vue`.
6. **No direct `localStorage` outside persisted Pinia stores.**
7. **Repeated pattern rule.** Any UI pattern appearing ≥ 2 times with equivalent structure MUST be promoted to an atom/molecule in the same PR.
8. **Invalidation locality.** `queryClient.invalidateQueries` is forbidden outside `composables/queries/**`. Each mutation composable owns its invalidation set.
9. **Focus indicators mandatory.** No `outline:none` / `focus:outline-none` without a compensating `focus-visible:ring-*`.
10. **Motion tokens only.** No `transition-*` > 80ms that doesn't derive from `--motion-*` tokens.
11. **Feature folders for domain organisms.** Organisms coupled to domain types (CollectionEntry, VolumeEntry, WishlistItem) live under `features/<domain>/organisms/`; only truly cross-domain organisms go to `components/organisms/`.
12. **Atomic Design naming.** Atoms `A*`, molecules `M*`, organisms `O*` or domain-named (e.g. `MangaHero`).

### Create skill `/.claude/skills/front-atomic-design/`

Files:
- `SKILL.md` — frontmatter: `name: front-atomic-design`, `description: Atomic Design + Editorial Dark design system for the Ziggytheque Vue 3 frontend. MUST trigger on any change under front/src/**. Enforces page LOC budgets, composable-only data fetching, design-token usage, Headless UI primitives, and the feature-folder structure.` Body references the hard rules above, the folder structure, the atom/molecule/organism inventory, and includes a pre-edit checklist for Claude (is this a page? — does it import `useQuery`? — is there an existing atom for this? — are tokens used instead of hex?).
- `references/tokens.md` — canonical token values + how to consume them.
- `references/atoms.md` — atom catalog with usage examples.
- `references/molecules.md` — molecule catalog.
- `references/composables.md` — queries vs ui composables, naming, invalidation locality.

Wire-up: the skill is "MUST trigger" so every future edit to `front/src/**` loads it — mirrors how `project-quality-setup` works.

---

## Execution order (single commit, phased internally)

Land everything in one PR per the user's decision + the project's one-commit-per-PR rule. Internally proceed in this order so every intermediate state compiles and tests pass:

0. **Tokens + deps + theme prune** — install packages, create `design/`, rewrite `assets/main.css`, simplify `useThemeStore`, rename `BaseThemeSwitch` → `MSegmentedControl`. Visual QA every page.
1. **Atoms + core molecules** — build all ~20 atoms + 8 highest-leverage molecules (`MModal`, `MConfirmDialog`, `MSearchInput`, `MAutocomplete`, `MStatCard`, `MStatPill`, `MCoverImage`, `MContextMenu`, `MSegmentedControl`). Each atom ships with a `.spec.ts` (snapshot + `axe` clean + prop-variant smoke).
2. **Composables + layouts** — implement `composables/queries/**` and `composables/ui/**`; create `layouts/AppLayout.vue` + `layouts/AuthLayout.vue`; extend router with `meta.layout`; move MainLayout code into the layout + `O*` organisms; delete old `MainLayout.vue`.
3. **Refactor bloated pages** — MangaDetailPage → ~140 LOC, AddMangaPage → ~120, WishlistPage → ~90. Convert one organism at a time; don't delete old inline markup until the new organism is wired. Smoke test (mount + first query + one happy path) per page.
4. **Refactor remaining pages** — CollectionPage, DashboardPage, NotificationsPage, GatePage. Rename `MangaCard` → `MangaCoverCard`, extract its progress bar to `AProgressBar`.
5. **Motion + a11y polish** — `v-motion` staggering, layout page transition, focus ring audit, skip link, landmarks, milestone glows, reduced-motion verified, `axe` checks on atoms.
6. **Test hardening** — minimum contract met: atoms (snapshot + axe + prop variant), molecules (+ 1 interaction), organisms (mount + 1 critical path with stubbed children), queries (unit with mocked axios), pages (smoke mount + 1 happy-path). Add `qa` npm script: `lint:check && type-check && test`.
7. **Docs/skill update** — write the new `.claude/CLAUDE.md` front section + hard rules; create `.claude/skills/front-atomic-design/` with `SKILL.md` + references. This is the **last** step so the rules match what was built.
8. **Dedupe API** — remove `purchaseVolume` from `api/wishlist.ts` (keep in `collection.ts`); update wishlist composable to call collection's.

---

## Critical files

New:
- `front/src/design/{tokens,daisy,typography,motion}.css`
- `front/src/layouts/{AppLayout,AuthLayout}.vue`
- `front/src/composables/queries/*.ts` (6 files)
- `front/src/composables/ui/*.ts` (7 files)
- `front/src/features/{collection,wishlist,add,dashboard,notifications,gate}/...`
- `front/src/components/atoms/A*.vue` (~20) + `__tests__/*.spec.ts`
- `front/src/components/molecules/M*.vue` (~15)
- `front/src/components/organisms/O{AppSidebar,BottomNav,SettingsSheet,ToastHost,BatchActionBar}.vue`
- `front/tailwind.config.ts` (if needed beyond v4 `@theme`)
- `.claude/skills/front-atomic-design/SKILL.md` + `references/*.md`

Rewritten:
- `front/src/assets/main.css`
- `front/src/App.vue` (dynamic layout)
- `front/src/router/index.ts` (meta.layout)
- `front/src/stores/useThemeStore.ts` (light|dark|system)
- `.claude/CLAUDE.md` (Frontend section + new Hard Rules section)

Deleted:
- `front/src/components/organisms/MainLayout.vue` (→ `layouts/AppLayout.vue` + extracted organisms)
- `front/src/components/organisms/EnrichVolumeModal.vue` (→ `MModal` + `EnrichPanel` + `VolumeActionsPanel`)
- `front/src/components/atoms/BaseHeartRating.{vue,css}`, `BaseThemeSwitch.vue`, `BaseToast.vue`, `LanguageSwitcher.vue` (replaced by `A*` / `M*` equivalents)
- `front/src/components/molecules/GenrePieChart.vue` (→ `features/dashboard/organisms/GenreBreakdownChart.vue`)
- `front/src/components/organisms/MangaCard.vue` (→ `features/collection/organisms/MangaCoverCard.vue`)
- `purchaseVolume` export from `api/wishlist.ts`

---

## Verification

Local:
```bash
# inside front/
npm install
npm run type-check     # vue-tsc clean
npm run lint           # eslint + LOC budget check
npm run test           # vitest: atoms snapshot+axe, composables unit, pages smoke
npm run dev            # Vite; visually walk every route
```

Container:
```bash
make up
# open http://localhost:5173
# login via gate; walk: dashboard → collection → detail → add → wishlist → notifications
# toggle theme switcher (light/dark/system) — verify no flash, prefers-color-scheme honored
# open DevTools → emulate prefers-reduced-motion → verify motion disabled
# tab through every page — verify focus ring visible everywhere, skip link works
```

Accessibility:
- Run Lighthouse a11y audit on every route; target ≥ 95.
- `axe` CLI (or `@axe-core/playwright` in e2e if added later) on gate, dashboard, collection, detail, wishlist, add — zero violations.

Page LOC budgets (CI check):
- `find front/src -path '*/pages/*.vue' -exec wc -l {} +` — every page ≤ 200 lines.

Skill verification:
- Open a new Claude Code session in the project; edit any file under `front/src/`; confirm `front-atomic-design` skill loads and its rules are surfaced.

Screenshot comparison (manual):
- Capture before/after of dashboard, collection grid, detail page, wishlist for the PR description.
