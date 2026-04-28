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
- `PriceCode/` — PriceCode entity, full CRUD (GET/POST/PATCH/DELETE /api/price-codes)
- `Manga/` — Manga + Volume entities, ExternalApiClientInterface (prepared, NullMangaApiClient stub)
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
- API layer: api/client.ts (axios), api/auth.ts, manga.ts, collection.ts, wishlist.ts, priceCode.ts, stats.ts, notification.ts
- i18n: vue-i18n, fr.json + en.json, FR default

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
