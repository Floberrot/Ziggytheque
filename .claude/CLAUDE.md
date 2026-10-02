# Ziggytheque — Project Instructions

## Testing — Mandatory on Every Feature

**Tests are part of feature delivery, never a separate phase.**

Every time a feature is planned, developed, or removed — this rule is non-negotiable:

| Change | Required test action |
|---|---|
| New HTTP endpoint | Add a functional test in `back/tests/Functional/` covering success + all error paths |
| Modified endpoint (request/response shape, status codes) | Update the corresponding functional test to reflect the new contract |
| Deleted endpoint | Delete the corresponding functional test |
| New domain entity / VO / enum | Add a unit test in `back/tests/Unit/` covering construction and all public methods |
| New domain service or application handler with pure logic | Add a unit test covering all branches |
| Modified domain object or handler | Update the unit test to match the new behaviour |
| Deleted domain object or handler | Delete the corresponding unit test |

**Rules:**
- A feature PR that adds or changes production code without touching `tests/` is **incomplete** — do not mark work as done.
- Unit tests (`back/tests/Unit/`) cover pure domain objects: entities, VOs, enums, exceptions, domain services. No kernel, no DB, no HTTP.
- Functional tests (`back/tests/Functional/`) boot the real Symfony kernel and hit real PostgreSQL. They test every HTTP status code the endpoint can return.
- Use `NullMangaApiClient` and `when@test:` service overrides in `config/services.yaml` to stub external HTTP calls — never let tests reach the real internet.
- The DAMA PHPUnit extension (configured in `phpunit.dist.xml`) wraps each test in a savepoint; no manual DB cleanup is needed between tests.

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
- `Manga/` — Manga + Volume entities (price lives on Volume). A Manga is one *series* = work × publisher (`edition`) × special edition (`specialEdition`, free text, null = standard run). French catalogue (`Domain/Catalogue`, BnF + Google Books fallback)
- `Collection/` — CollectionEntry + VolumeEntry, toggle owned/read per volume
- `Wishlist/` — WishlistItem, purchase moves to collection
- `Stats/` — GetStats query (totalOwned, totalRead, totalWishlist, collectionValue, genreBreakdown)
- `Notification/` — Notification entity, stub endpoints

## Code Style

- **Never use FQCN for PHP built-in classes** — always add a `use` import at the top of the file. Applies to `\DateTimeImmutable`, `\DateTimeInterface`, `\SimpleXMLElement`, `\Throwable`, `\RuntimeException`, etc.

- **Variable names must be full and descriptive** — no single-letter variables, no abbreviations. Use the domain context: `$entry` not `$e`, `$volume` not `$v`, `$command` not `$cmd`. Full rules: see `backend.md` R10.

- **All paginated queries and results use Shared base classes** — extend `AbstractPaginatedQuery` for any query with `page`/`limit`, and extend `PaginatedResult<T>` for the result VO. Never inline pagination fields. Full rules: see `backend.md` R11.

```php
// Bad
$dt = new \DateTimeImmutable('2026-04-01');

// Good
use DateTimeImmutable;
$dt = new DateTimeImmutable('2026-04-01');
```

## Doctrine Mapping Rules

These rules keep `doctrine:schema:validate` green. A failure means real drift between entity metadata and the DB — it must always pass.

### Enum columns — `length` must match the migration DDL

Doctrine ORM 3 defaults to `VARCHAR(255)` for string-backed PHP enums when no `length` is given. Only add `length:` when the migration intentionally uses a narrower column, and the two must agree exactly.

```php
// Safe — Doctrine generates VARCHAR(255), migration must also use VARCHAR(255)
#[ORM\Column(enumType: StatusEnum::class)]

// Explicit size — both entity and migration must agree on 20
#[ORM\Column(enumType: EventTypeEnum::class, length: 20)]
```

After adding/changing an enum column, run `make migration` to let Doctrine generate the DDL and verify sizes match.

### Boolean/string columns with a DB-level DEFAULT

If a migration creates a column with `DEFAULT value`, the entity must also declare `options: ['default' => value]`. Without it Doctrine's metadata disagrees with the DB and `schema:validate` fails.

```php
// Bad — migration has DEFAULT FALSE but entity metadata has no default → drift
#[ORM\Column]
public bool $notificationsEnabled = false,

// Good — metadata matches the DB constraint
#[ORM\Column(options: ['default' => false])]
public bool $notificationsEnabled = false,
```

The PHP property default (`= false`) controls the in-memory object value; `options: ['default' => ...]` declares the DB-level DEFAULT. Both are needed when the column carries a DB default.

### FK / index names — always use `make migration`, never write hash names by hand

Doctrine generates names as `strtoupper(PREFIX . '_' . implode('', array_map('dechex', array_map('crc32', $columns))))`. One wrong column or wrong column order silently produces a different hash and causes `schema:validate` to fail.

```bash
# After any entity change, regenerate the migration to get Doctrine's exact names:
make migration
```

Human-readable names (e.g. `fk_volumes_manga`) will always conflict with Doctrine's hash names. Never write them manually in `addSql()`.

## Key Patterns
- Hexagonal: Domain → Application → Infrastructure
- CQRS via Symfony Messenger (command.bus / query.bus / event.bus), default_bus: command.bus
- No try/catch in controllers — ExceptionListener handles all DomainExceptions
- #[MapRequestPayload] on every controller that reads a request body
- `final readonly` on every class that is not extended
- Full architecture rules with code examples: **see `.claude/backend.md`** — mandatory reading before any backend work

## Frontend (front/src/)
- Atomic Design: atoms (Base*) → molecules → organisms → pages
- Only pages call useQuery/useMutation
- Auth: useAuthStore (sessionStorage), Bearer JWT via axios interceptor
- Stores: useAuthStore, useThemeStore (dark default), useUiStore (toasts)
- API layer: api/client.ts (axios), api/auth.ts, manga.ts, collection.ts, wishlist.ts, stats.ts, notification.ts
- Covers: always `BaseCover` (or `BaseLazyImage` + `coverUrl()`), never a raw `<img :src>` — it proxies anti-hotlink hosts (Google Books, MangaDex, BnF) through `/proxy/cover`, upgrades `http://`, and falls back to an icon on a broken or placeholder image
- Collection grid: one card per work (`groupByWork`: same folded title, or a title starting with it by the same author); several editions show as a stacked `MangaCard` that opens `WorkEditionsSheet`
- Quick actions: right click / long press (`useLongPress`) on a collection or dashboard card → `CollectionQuickActions` (open, follow, rate, remove); the mutations live in the page
- Mobile (< lg): top header (logo + Actualités) and a bottom bar (Accueil, Collection, **Ajouter** raised in the middle, Souhaits, Réglages sheet); `pb-mobile-nav` / `mb-mobile-nav` keep content and toasts above it
- i18n: vue-i18n, fr.json + en.json, FR default

## Routes (frontend)
- /gate — public password gate
- / → /dashboard (protected, MainLayout sidebar)
- /collection, /collection/:id, /wishlist, /add, /notifications

## API Endpoints
- POST   /api/auth/gate
- GET    /api/manga?q=, GET /api/manga/:id, POST /api/manga, POST /api/manga/:id/volumes
- GET    /api/catalogue/search?q=&mode=title|author|isbn → series (work × publisher × special edition) + the user's owned tomes
- GET    /api/catalogue/edition?workTitle=&publisher=&specialEdition= → one series with every known tome
- POST   /api/catalogue/add { workTitle, publisher, specialEdition, author, coverUrl, volumeCount, volumes, ownedNumbers } → creates the series (all tomes) if needed, marks the picked tomes owned (201 new entry / 200 existing)
- POST   /api/catalogue/scan { isbn } → one scanned tome in the collection, its series created if needed (404 = no French edition)
- POST   /api/scan/sessions {} (free, 30 min — phone scans a shelf) or { mangaId, volumeId } (one tome, 10 min)
- GET/POST /api/collection, GET/DELETE /api/collection/:id — list default sort = by work (A → Z), `sort=added_desc|rating_desc|rating_asc`
- PATCH  /api/collection/:id/status
- PATCH  /api/collection/:id/volumes/:veId/toggle { field: isOwned|isRead }
- GET/POST /api/wishlist, DELETE /api/wishlist/:id, POST /api/wishlist/:id/purchase
- GET    /api/stats
- GET    /api/notifications, PATCH /api/notifications/:id/read
- GET    /messenger (Basic auth)

## Add flow — manga first, French editions only
- The user finds a *tome* (scan, title or author); the whole series follows (created with every tome, the others stay untracked).
- Catalogue: `App\Manga\Domain\Catalogue\CatalogueInterface` → `CachedCatalogue` (1 h) → `FallbackCatalogue` (BnF SRU first, Google Books only when BnF has nothing). Test env: `App\Tests\Doubles\Manga\InMemoryCatalogue`.
- **Special editions are discovered, never predicted**: `CatalogueTitleParser` reads the title structure (BnF ISBD "Berserk : prestige. 3", "Berserk. 5 (Éd. prestige)", Google "One Piece - Édition originale - Tome 3", "Berserk - 5"); whatever sits in the edition slot is kept verbatim ("Éd." is spelled out "Édition"). A qualifier written *after* the tome number is an edition when it is an edition statement (starts or ends with the word "édition") or when it repeats on several tomes (`CatalogueEditionAssembler`). Never add a list of edition names.
- Series identity (`EditionIdentity`): folded title + publisher imprint (`PublisherNormalizer`) + folded special edition, the word "édition" around the name aside ("Prestige" = "Édition prestige").
- Catalogue noise: only printed books with an ISBN are kept (BnF `dc:type` must be printed text; every type / Google category is checked against films, music, software, video games — `EditionRelevanceFilter`).
- A scanned ISBN always finds its own tome: the records the catalogue returns *for* that ISBN carry it (`CatalogueSearch::identifyIsbn`).
- A series created from the catalogue is enriched in the background (genre / summary / author) from Jikan (`ExternalApiClientInterface`, exact-title match only) via `EnrichMangaMessage` (async).
- Front: `pages/AddMangaPage.vue` (tabs Rechercher / Scanner / À la main), `CatalogueEditionSheet` (tome picker), `ScanFeed` (batch scan), `ScanViewfinder` (framing guide, flash + vibration on read, torch); phone scanning via `/scan/:token?batch=1`. `useBarcodeScanner` uses the browser `BarcodeDetector` when it reads EAN-13, else zxing (1D reader, EAN hints, rear camera in HD).

## Docker (local dev)
- back: http://localhost:8000 — FrankenPHP
- app:  http://localhost:5173 — Vite
- db:   localhost:5432 — PostgreSQL 17
- mailer: http://localhost:8025 — Mailpit (local catcher only)
- worker: Messenger consumer

`make dev` prints these URLs after startup so they're ⌘-clickable from the terminal.

## Production (Railway)
- Public URL: **https://www.ziggytheque.fr** (apex `ziggytheque.fr` redirects 301 → www via OVH)
- 4 Railway services: backend (FrankenPHP), worker (Messenger consumer), frontend (nginx SPA), PostgreSQL
- Emails: **Resend** — domain `ziggytheque.fr` verified, sender `notifications@ziggytheque.fr`, `MAILER_DSN=resend+api://KEY@default`. Full setup: `docs/resend.md`
- Frontend nginx proxies `/api` and `/proxy` to the backend via `BACKEND_URL` (internal Railway URL), so the SPA stays same-origin
- CORS prod value: `CORS_ALLOW_ORIGIN=^https://(www\.)?ziggytheque\.fr$`
- **Secrets never live in `back/.env`** (it ships in the image): their keys stay there with an EMPTY value, local values go in `back/.env.dev`, test values in `back/.env.test`, real values in Railway. A weak value (empty, short, `CHANGEME`, an old committed default — `SecretStrength`) keeps its feature closed: `/messenger` accepts nobody (MONITOR_PASSWORD < 12 chars), the admin gate answers 503 (GATE_PASSWORD < 12 chars). `bin/console app:security:check-secrets` runs at container start and lists the weak ones (names only).
- **Env var sync at deploy**: a `sync-env` job (in both deploy workflows) compares the env vars declared in the repo's `.env` files against what's set on the target Railway environment and creates any **new** one with a sentinel value `CHANGEME` (the Railway CLI can't set empty; `--skip-deploys`, never edits existing values) so it only needs its real value typed in Railway. Always exits 0 — never blocks a deploy. Script + ignore-list (`IGNORE_KEYS` exact + `IGNORE_PATTERNS` globs, incl. `*_BASE_URL`): `scripts/railway-sync-env-keys.sh`; full doc: `docs/railway-env-sync.md`. When you add a `back/.env` var whose committed default IS the prod value, add its key to `IGNORE_KEYS` so it isn't shadowed by a `CHANGEME` placeholder.

## First time setup
```
make setup  # starts containers, waits for back, generates JWT keys, runs migrations
```

---

## Docker Gotchas (learned in production)

### 1. FrankenPHP — always disable auto-HTTPS

FrankenPHP/Caddy enables auto-HTTPS by default. In Docker/Railway this causes a 308
redirect from HTTP to HTTPS, breaking any HTTP proxy pointed at the container.

**Local dev (docker-compose):** Set `SERVER_NAME: "http://:80"` in the `back` service
environment (used by FrankenPHP's default Caddyfile in `Dockerfile.dev`).

```yaml
# docker-compose.yml — back service
environment:
  SERVER_NAME: "http://:80"
```

**Production (Railway):** Bind directly to `$PORT` in the Caddyfile — do NOT use
`SERVER_NAME` for production. Railway injects `PORT` at runtime.

```caddy
# back/Caddyfile
:{$PORT:80} {
  ...
}
```

Never use bare `SERVER_NAME: ":80"` or omit it in local dev — both trigger TLS.

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
