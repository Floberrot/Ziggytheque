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
