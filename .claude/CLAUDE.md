# Ziggytheque — Project Instructions

## Git Discipline

- **One commit per PR** — a PR must land as a single commit. Prefer `git commit --amend` to add changes to the current commit; use `git rebase -i` to squash only if amend is not possible.
- **Never create a new commit when one already exists on the branch** — always amend instead.
- **Commits must be authored solely by the repo owner** — never set Claude or any AI assistant as the author. Always preserve the user's git identity (`user.name` / `user.email`). Never pass `--author` or alter git config.

## Stack
- Backend: Symfony 8 + PHP 8.4 + FrankenPHP + PostgreSQL 17 (`back/`)
- Frontend: Vue 3 + TypeScript + Vite + DaisyUI (`front/`)
- Docker: 5 containers via `docker-compose.yml` at root

## Auth (Gate)
- Single password from `GATE_PASSWORD` env var — no user accounts
- `POST /api/auth/gate { password }` → JWT
- All `/api/*` routes require Bearer JWT
- `/messenger` uses HTTP Basic (MONITOR_USER / MONITOR_PASSWORD)

## Bounded Contexts (back/src/)
- `Shared/` — CommandBus, QueryBus, EventBus interfaces + Messenger implementations + ExceptionListener
- `Auth/` — GateUser, GateUserProvider, GateCommand/Handler, GateController
- `PriceCode/` — PriceCode entity, full CRUD (GET/POST/PATCH/DELETE /api/price-codes)
- `Manga/` — Manga + Volume entities, ExternalApiClientInterface (prepared, NullMangaApiClient stub)
- `Collection/` — CollectionEntry + VolumeEntry, toggle owned/read per volume
- `Wishlist/` — WishlistItem, purchase moves to collection
- `Stats/` — GetStats query (totalOwned, totalRead, totalWishlist, collectionValue, genreBreakdown)
- `Notification/` — Notification entity, stub endpoints

## Key Patterns
- Hexagonal: Domain → Application → Infrastructure
- CQRS via Symfony Messenger (command.bus / query.bus / event.bus), default_bus: command.bus
- No try/catch in controllers — ExceptionListener handles all DomainExceptions
- #[MapRequestPayload] on every controller that reads a request body
- `final readonly` on every class that is not extended

## Frontend (front/src/)

### Architecture
- **Atomic Design**: atoms (A*) → molecules (M*) → organisms (O*) → pages
- **Folder Structure**:
  - `components/{atoms,molecules,organisms}` — domain-agnostic primitives
  - `features/{domain}/{organisms,pages}` — domain-coupled organisms/pages
  - `composables/{queries,ui}` — data fetching (queries only) + UI helpers
  - `layouts/{AppLayout,AuthLayout}` — shell layouts
  - `design/{tokens,typography,daisy,motion}.css` — design tokens & theme
  - `stores/` — Pinia stores (useAuthStore, useThemeStore, useUiStore)
  - `api/` — axios client + endpoint functions
- **Stores**: useAuthStore, useThemeStore (light|dark|system), useUiStore
- **Theme**: Editorial Dark (ziggy-dark, ziggy-light) via DaisyUI; no palette picker
- **Auth**: Bearer JWT via axios interceptor; useGate gate to POST /api/auth/gate
- **i18n**: vue-i18n, fr.json + en.json, FR default
- **Icons**: @iconify/vue + lucide icons; use AIcon component only
- **API layer**: api/client.ts (axios), api/{auth,manga,collection,wishlist,stats,notification}.ts

## Routes (frontend)
- /gate — public password gate
- / → /dashboard (protected, MainLayout sidebar)
- /collection, /collection/:id, /wishlist, /add, /price-codes, /notifications

## API Endpoints
- POST   /api/auth/gate
- GET    /api/manga?q=, GET /api/manga/:id, POST /api/manga, POST /api/manga/:id/volumes
- GET/POST /api/collection, GET/DELETE /api/collection/:id
- PATCH  /api/collection/:id/status
- PATCH  /api/collection/:id/volumes/:veId/toggle { field: isOwned|isRead }
- GET/POST /api/wishlist, DELETE /api/wishlist/:id, POST /api/wishlist/:id/purchase
- GET/POST/PATCH/DELETE /api/price-codes, PATCH /api/price-codes/:code
- GET    /api/stats
- GET    /api/notifications, PATCH /api/notifications/:id/read
- GET    /messenger (Basic auth)

## External API (Google Books)
- Interface: App\Manga\Domain\ExternalApiClientInterface
- DTOs: ExternalMangaDto (externalId, title, edition, author, summary, coverUrl, genre, language, totalVolumes), ExternalVolumeDto
- Implementation: App\Manga\Infrastructure\ExternalApi\GoogleBooksMangaApiClient
- Requires: GOOGLE_BOOKS_API_KEY env var in back/.env
- Searches French editions only (langRestrict=fr), appends "+manga" to query
- Endpoint: GET /api/manga/external?q=... → ExternalMangaResult[] (JWT required)
- Frontend search: composable useExternalSearch hits /api/manga/external via authenticated axios client
- Swap implementation by changing alias in services.yaml (NullMangaApiClient available as stub)

## Docker
- back: http://localhost:8000 — FrankenPHP
- app:  http://localhost:5173 — Vite
- db:   localhost:5432 — PostgreSQL 17
- mailer: http://localhost:8025 — Mailpit
- worker: Messenger consumer

## First time setup
```
make setup  # starts containers, waits for back, generates JWT keys, runs migrations
```

---

## Docker Gotchas (learned in production)

### 1. FrankenPHP — always disable auto-HTTPS in Docker
FrankenPHP/Caddy enables auto-HTTPS by default. In Docker this causes a 308 redirect
from HTTP to HTTPS, breaking any HTTP proxy pointed at the container.

**Rule:** Always set `SERVER_NAME: "http://:80"` in the `back` service environment.
Never use bare `SERVER_NAME: ":80"` or omit it — both trigger TLS.

```yaml
# docker-compose.yml — back service
environment:
  SERVER_NAME: "http://:80"
```

### 2. Vite proxy — use Docker service name, never localhost
When Vite runs inside a Docker container, `localhost` resolves to that container itself,
not the host machine. Proxying to `http://localhost:8000` → ECONNREFUSED.

**Rule:** Always pass `BACKEND_URL` as an env var to the `app` container and use it in
`vite.config.ts`. Default to `http://localhost:8000` for local dev outside Docker.

```yaml
# docker-compose.yml — app service
environment:
  BACKEND_URL: http://back:80
```

```ts
// vite.config.ts
proxy: {
  '/api': {
    target: process.env.BACKEND_URL ?? 'http://localhost:8000',
  },
},
```

### 3. Migrations — must run explicitly after first boot
Tables do not exist until `doctrine:migrations:migrate` is run. Any API call that hits
the database will return a 500 `TableNotFoundException` until then.

**Rule:** Always run migrations as part of first-time setup. `make setup` handles this.
After adding a new entity or migration, run:

```bash
make migrate
# or directly:
docker compose exec back php bin/console doctrine:migrations:migrate --no-interaction
```

Never deploy or test against a fresh database without running migrations first.

---

## Frontend Architecture — Hard Rules

### Atomic Design Discipline
1. **No raw HTML in pages.** Files under `pages/` or `features/*/pages/` MUST NOT contain:
   - Raw `<button>`, `<input>`, `<select>`, `<textarea>` — use AButton, AInput, ASelect, ATextarea
   - Inline `<svg>` — use `<AIcon name="..." />`
   - DaisyUI classes (`btn-*`, `badge-*`, `card-*`) — wrap with atoms/molecules
2. **Atoms**: No store or query access. Pure props in → events out. Snapshot tests + axe scans required.
3. **Molecules**: Atoms + small UI logic (state, conditional rendering). No domain knowledge.
4. **Organisms**: Domain-specific or cross-domain. Can use queries/stores. Reusable across pages.
5. **Feature Folders**: Domain organisms live under `features/<domain>/organisms/`; only cross-domain in `components/organisms/`.

### Data Fetching
6. **Centralized queries**: `useQuery` / `useMutation` / `useInfiniteQuery` ONLY inside `composables/queries/**/*.ts`.
   - Pages/organisms import composables, not query functions directly.
   - Each mutation composable owns its invalidation set (no `queryClient.invalidateQueries` outside composables/queries/).
7. **Query composables**: One file per API surface (useCollectionQueries, useMangaQueries, etc.).
   - Export 1 hook per domain operation.
   - Mutations handle loading/error via `isPending`, `isError` properties.

### UI Composables
8. **UI helpers**: `useToast`, `useConfirm`, `useBreakpoint`, `useBatchSelection`, `useKeyboardShortcut`, `useCoverUrl`, `useContextMenu`.
   - Live under `composables/ui/`, not scattered.
   - Use for cross-cutting concerns only (not state management).

### Page LOC Budgets
9. **Hard caps** (enforce via lint or CI):
   - Pages: **max 200 LOC**. Target: 100–150.
   - Organisms: **max 300 LOC**.
   - Molecules: **max 150 LOC**.
   - Atoms: **max 100 LOC**.

### Design System
10. **Tokens first**: Use CSS custom properties from `design/tokens.css`:
    - Motion: `--motion-fast` (150ms), `--motion-base` (220ms), `--motion-slow` (360ms).
    - Radii: `--radius-xs` through `--radius-full`.
    - Shadows: `--shadow-xs` through `--shadow-glow-primary`.
    - No hardcoded durations > 80ms without `--motion-*`.
11. **Single theme**: Only `ziggy-light` and `ziggy-dark` registered. Palette picker deleted.
    - System mode supported via `useThemeStore` (light|dark|system).
    - No inline hex colors; use DaisyUI semantic classes or Tailwind utilities.
12. **Icons**: `<AIcon name="lucide:..." />` only. No inline SVG except AIcon.vue, AHeartRating.vue.

### Accessibility
13. **Focus ring mandatory**: No `outline:none` / `focus:outline-none` without `focus-visible:ring-*` compensation.
14. **Landmarks**: AppLayout emits `<header>`, `<nav aria-label="...">`, `<main id="main">`. Skip link present.
15. **ARIA**: 
    - Form inputs: `aria-invalid`, `aria-describedby` for errors.
    - Dialogs: Headless UI `Dialog` (focus trap, Esc, backdrop, role).
    - Lists: `role="listbox"`, `role="option"` with `aria-selected` in batch mode.
    - Toasts: `role="status" aria-live="polite"` (or `role="alert" aria-live="assertive"` for errors).
16. **Motion**: Respect `prefers-reduced-motion`; animations fade/disabled entirely in reduce mode.

### Naming & Organization
17. **Component prefixes**:
    - Atoms: `A*` (AButton.vue, AIcon.vue).
    - Molecules: `M*` (MModal.vue, MSearchInput.vue).
    - Organisms: `O*` (OAppSidebar.vue) or domain-named (MangaHero.vue, CollectionFilterBar.vue).
    - Pages: named naturally (DashboardPage.vue, MangaDetailPage.vue).
18. **Repeated pattern rule**: Any UI pattern appearing ≥ 2 times with equivalent structure MUST be promoted to a reusable atom/molecule in the same PR.

### API Deduplication
19. **No duplicate functions**: `purchaseVolume` exists in `collection.ts`; removed from `wishlist.ts`.
    - Wishlist composable calls collection's purchase function.

### Testing Baseline
20. **Minimum contract**:
    - Atoms: snapshot + axe audit + prop-variant smoke test.
    - Molecules: ↑ + 1 interaction test.
    - Organisms: mount + stub children + 1 happy-path integration.
    - Pages: smoke mount + 1 critical user flow (assumes composables are tested separately).
    - Queries: unit tests with mocked axios.
