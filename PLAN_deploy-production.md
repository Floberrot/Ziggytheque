# Plan: Deploy ZiggyTheque to Production (Railway)

## Context & Goal

ZiggyTheque currently has only a development Docker setup (`back/Dockerfile.dev`, `docker-compose.yml`).
This plan adds everything needed to deploy to production on Railway, mirroring what the sibling project
**Ziggy** does: three Railway services (backend FrankenPHP + Messenger worker + frontend Nginx), each with
its own Dockerfile and `railway.json`. It also wires up the Deptrac architecture linter (already installed in
`composer.json` as `deptrac/deptrac` but missing its config file) and adds a `deptrac` target to the
Makefile so `make qa` enforces hexagonal boundaries locally and in CI.

## Scope

**Included:**
- `back/deptrac.yaml` — architecture boundary rules for all 6 bounded contexts
- Makefile — `deptrac` target wired into `make qa`
- `back/docker-entrypoint.sh` — production entrypoint (cache warmup + migrate + start FrankenPHP)
- Root `Dockerfile` — multi-stage production build (Vue SPA → FrankenPHP)
- `front/Dockerfile` — nginx production image serving the Vue SPA with API proxy
- `front/nginx.conf.template` — nginx template consuming `$PORT` and `$BACKEND_URL` env vars
- `db/Dockerfile` — PostgreSQL 17 Alpine service
- `railway.json` (root) — Railway config for the backend service
- `worker/railway.json` — Railway config for the Messenger consumer service
- `front/railway.json` — Railway config for the frontend service
- `db/railway.json` — Railway config for the PostgreSQL service
- Makefile — `logs-worker` target

**Out of scope:**
- Setting up a Railway account or project (manual step, documented in QA)
- CI/CD pipeline (GitHub Actions) — deploy on push is handled by Railway natively

## Architecture Overview

```
Railway Service 1 — "db"
  Built from db/Dockerfile:
    postgres:17-alpine with POSTGRES_USER=ziggy, POSTGRES_PASSWORD=ziggy, POSTGRES_DB=ziggytheque
  Exposes: :5432 internally on network, hostname 'db'
  
Railway Service 2 — "backend"
  Built from root Dockerfile (multi-stage):
    Stage "frontend": node:22-alpine → npm ci → npm run build → dist/
    Stage "base":     dunglas/frankenphp:1-php8.4 → PHP extensions
    Stage "prod":     composer install --no-dev + copy dist → JWT keypair → entrypoint
  Serves: FrankenPHP on :80, all /api/* routes
  Depends on: db service (DATABASE_URL references db:5432)
  Env vars: DATABASE_URL, JWT_PASSPHRASE, GATE_PASSWORD, APP_SECRET, CORS_ALLOW_ORIGIN,
            GOOGLE_BOOKS_API_KEY, MESSENGER_TRANSPORT_DSN

Railway Service 3 — "worker"
  Built from the SAME root Dockerfile (same prod image as backend, Railway shares build cache)
  Service root dir: /worker  →  reads worker/railway.json
  Overrides start command: php bin/console messenger:consume async --time-limit=3600 -vv
  Depends on: db service (shares DATABASE_URL from backend)
  Same env vars as backend (DATABASE_URL, APP_SECRET, JWT_PASSPHRASE, MESSENGER_TRANSPORT_DSN…)
  restart: ON_FAILURE (Railway restarts on crash/exit, --time-limit lets it recycle gracefully)

Railway Service 4 — "frontend"
  Built from front/Dockerfile:
    Stage "builder": node:22-alpine → npm ci → npm run build → dist/
    Stage "prod":    nginx:alpine → serve dist/ → proxy /api → $BACKEND_URL
  Env vars at runtime: PORT (set by Railway), BACKEND_URL (Railway backend internal URL)
  Build arg:          VITE_API_BASE_URL (empty — frontend uses relative /api calls)
```

---

## Step 1 — back/deptrac.yaml: Architecture boundary enforcement

**File:** `back/deptrac.yaml` *(create)*
**Why:** Deptrac is already installed (`deptrac/deptrac` in `require-dev`) but has no config — without
it the `vendor/bin/deptrac` binary does nothing. This file enforces hexagonal rules: Domain cannot
import Application/Infrastructure; Application cannot import Infrastructure; cross-context deps are
forbidden except through Shared.

```yaml
deptrac:
  paths:
    - ./src

  exclude_files:
    - '#.*Kernel\.php#'

  layers:
    # ── Auth ──────────────────────────────────────────────────────────────────
    - name: Auth_Domain
      collectors:
        - type: className
          regex: 'App\\Auth\\Domain\\.*'

    - name: Auth_Application
      collectors:
        - type: className
          regex: 'App\\Auth\\Application\\.*'

    - name: Auth_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Auth\\Infrastructure\\.*'

    # ── Collection ────────────────────────────────────────────────────────────
    - name: Collection_Domain
      collectors:
        - type: className
          regex: 'App\\Collection\\Domain\\.*'

    - name: Collection_Application
      collectors:
        - type: className
          regex: 'App\\Collection\\Application\\.*'

    - name: Collection_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Collection\\Infrastructure\\.*'

    # ── Manga ─────────────────────────────────────────────────────────────────
    - name: Manga_Domain
      collectors:
        - type: className
          regex: 'App\\Manga\\Domain\\.*'

    - name: Manga_Application
      collectors:
        - type: className
          regex: 'App\\Manga\\Application\\.*'

    - name: Manga_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Manga\\Infrastructure\\.*'

    # ── Notification ──────────────────────────────────────────────────────────
    - name: Notification_Domain
      collectors:
        - type: className
          regex: 'App\\Notification\\Domain\\.*'

    - name: Notification_Application
      collectors:
        - type: className
          regex: 'App\\Notification\\Application\\.*'

    - name: Notification_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Notification\\Infrastructure\\.*'

    # ── Stats ─────────────────────────────────────────────────────────────────
    - name: Stats_Domain
      collectors:
        - type: className
          regex: 'App\\Stats\\Domain\\.*'

    - name: Stats_Application
      collectors:
        - type: className
          regex: 'App\\Stats\\Application\\.*'

    - name: Stats_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Stats\\Infrastructure\\.*'

    # ── Wishlist ──────────────────────────────────────────────────────────────
    - name: Wishlist_Domain
      collectors:
        - type: className
          regex: 'App\\Wishlist\\Domain\\.*'

    - name: Wishlist_Application
      collectors:
        - type: className
          regex: 'App\\Wishlist\\Application\\.*'

    - name: Wishlist_Infrastructure
      collectors:
        - type: className
          regex: 'App\\Wishlist\\Infrastructure\\.*'

    # ── Shared (allowed everywhere) ───────────────────────────────────────────
    - name: Shared
      collectors:
        - type: className
          regex: 'App\\Shared\\.*'

  ruleset:
    # Domain layers: can only depend on Shared
    Auth_Domain:
      - Shared
    Collection_Domain:
      - Shared
    Manga_Domain:
      - Shared
    Notification_Domain:
      - Shared
    Stats_Domain:
      - Shared
    Wishlist_Domain:
      - Shared

    # Application layers: can depend on their own Domain + Shared
    Auth_Application:
      - Auth_Domain
      - Shared
    Collection_Application:
      - Collection_Domain
      - Shared
    Manga_Application:
      - Manga_Domain
      - Shared
    Notification_Application:
      - Notification_Domain
      - Shared
    Stats_Application:
      - Stats_Domain
      - Shared
    Wishlist_Application:
      - Wishlist_Domain
      - Shared

    # Infrastructure layers: can depend on Domain + Application of the same context + Shared
    Auth_Infrastructure:
      - Auth_Domain
      - Auth_Application
      - Shared
    Collection_Infrastructure:
      - Collection_Domain
      - Collection_Application
      - Shared
    Manga_Infrastructure:
      - Manga_Domain
      - Manga_Application
      - Shared
    Notification_Infrastructure:
      - Notification_Domain
      - Notification_Application
      - Shared
    Stats_Infrastructure:
      - Stats_Domain
      - Stats_Application
      - Shared
    Wishlist_Infrastructure:
      - Wishlist_Domain
      - Wishlist_Application
      - Shared

    # Shared can depend on nothing (it is the base layer)
    Shared: ~
```

> Run `docker compose exec back vendor/bin/deptrac analyse` after creating this file.
> If violations appear, investigate and fix them before proceeding — do NOT add `uncovered`
> or `ignore` entries unless you fully understand why and document it inline.

---

## Step 2 — Makefile: add `deptrac` target and wire into `qa`

**File:** `Makefile` *(modify)*
**Why:** `make qa` currently runs `php-qa` → `composer qa` which runs phpcs + phpstan + test, but
Deptrac is not included. Adding a dedicated `deptrac` target keeps it composable and mirrors the
Ziggy project's Makefile layout.

Find the `##@ Quality` section and replace it, and add `logs-worker` to the `##@ Backend` section:

```makefile
.PHONY: logs-worker
logs-worker: ## Tail Messenger worker logs
	$(DC) logs -f worker
```

```makefile
##@ Quality

.PHONY: deptrac
deptrac: ## Run Deptrac architecture boundary check
	$(BACK) vendor/bin/deptrac analyse

.PHONY: php-qa
php-qa: ## Run all PHP quality gates (style + stan + deptrac + tests)
	$(BACK) composer qa
	$(MAKE) deptrac

.PHONY: vue-qa
vue-qa: ## Run all Vue quality gates
	$(FRONT) npm run qa

.PHONY: qa
qa: php-qa vue-qa ## Run all quality gates (PHP + Vue)
```

> The existing `composer qa` script in `back/composer.json` already runs `phpcbf + phpcs + phpstan + test`.
> Deptrac runs after composer qa because it analyses the compiled class map which requires autoload to be up-to-date.
> `logs-worker` mirrors the `logs-worker` target in the Ziggy project Makefile.

---

## Step 3 — back/docker-entrypoint.sh: production entrypoint

**File:** `back/docker-entrypoint.sh` *(create)*
**Why:** The dev entrypoint (`docker-entrypoint.dev.sh`) only installs vendor if missing. The production
entrypoint must warm up the Symfony cache, run pending migrations, and then exec FrankenPHP. Railway
restarts the container on failure — migrations must be idempotent (Doctrine ensures this).

```sh
#!/bin/sh
set -e

echo "[entrypoint] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod

echo "[entrypoint] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[entrypoint] Starting FrankenPHP..."
exec "$@"
```

Make it executable: `chmod +x back/docker-entrypoint.sh` (or set in Dockerfile — see Step 4).

> Do NOT run `composer install` here. In production the image is built with vendor already present.
> Do NOT generate JWT keys here — they are generated at build time (see Step 4). Regenerating on
> every restart would invalidate existing sessions on each Railway container restart.

---

## Step 4 — Dockerfile (root): multi-stage production build

**File:** `Dockerfile` *(create at project root)*
**Why:** Railway's backend service points to the root `railway.json` which references this file.
Multi-stage: (1) build the Vue SPA, (2) build the PHP production image with vendor + SPA assets.
JWT keypair is generated at build time using the `JWT_PASSPHRASE` build arg so keys are stable
within a single image and regenerated (intentionally) on each new Railway deploy.

```dockerfile
# ── Stage 1: Frontend build ───────────────────────────────────────────────────
FROM node:22-alpine AS frontend

WORKDIR /app

COPY front/package.json front/package-lock.json ./
RUN npm ci

COPY front/ .
RUN npm run build
# Output: /app/dist/

# ── Stage 2: PHP base ─────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    apcu

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ── Stage 3: Production ───────────────────────────────────────────────────────
FROM base AS prod

ARG JWT_PASSPHRASE
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV SERVER_NAME="http://:80"

COPY back/ .

RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --classmap-authoritative

# Generate JWT keypair baked into the image
RUN JWT_PASSPHRASE=${JWT_PASSPHRASE} php bin/console lexik:jwt:generate-keypair --overwrite

# Copy built Vue SPA into Symfony public directory (served by FrankenPHP as static files)
COPY --from=frontend /app/dist /app/public/spa

COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
```

> **On the `public/spa` copy:** The frontend is served by the separate Nginx service (Service 2),
> so this `COPY` is kept as a fallback / future option. The Nginx service is the primary frontend
> delivery. If you want a true monolith later (single Railway service), configure the Caddyfile to
> serve `/app/public/spa` at `/` and only proxy PHP for `/api/*`.
>
> **On `SERVER_NAME="http://:80"`**: This prevents FrankenPHP/Caddy from enabling auto-HTTPS
> which would cause 308 redirects in Railway's HTTP proxy. See Docker Gotchas in CLAUDE.md.
>
> **On `--classmap-authoritative`**: Speeds up production autoloading. Only safe after
> `--optimize-autoloader` and when no dynamic class loading occurs (standard for this project).

---

## Step 5 — front/Dockerfile: nginx production image

**File:** `front/Dockerfile` *(create)*
**Why:** Railway's frontend service uses this to build and serve the Vue SPA. Nginx handles
SPA routing (`try_files`) and proxies `/api/` to the backend service at runtime via `$BACKEND_URL`.

```dockerfile
# ── Stage 1: Build ────────────────────────────────────────────────────────────
FROM node:22-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# VITE_API_BASE_URL is intentionally left empty: the frontend uses relative /api calls
# which nginx proxies to $BACKEND_URL at runtime. No value needed at build time.
ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

COPY . .
RUN npm run build
# Output: /app/dist/

# ── Stage 2: Production ───────────────────────────────────────────────────────
FROM nginx:alpine AS prod

COPY --from=builder /app/dist /usr/share/nginx/html

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

> Nginx's official image auto-processes files in `/etc/nginx/templates/` by running
> `envsubst` on them before starting. This is how `$PORT` and `$BACKEND_URL` are injected
> at container startup without rebuilding the image.

---

## Step 6 — front/nginx.conf.template: SPA + API proxy config

**File:** `front/nginx.conf.template` *(create)*
**Why:** Handles SPA routing (all unknown paths → `/index.html`) and proxies `/api/` to the
backend Railway service URL. Uses `$PORT` (set by Railway automatically) and `$BACKEND_URL`
(set manually in Railway frontend service env vars).

```nginx
server {
    listen ${PORT};
    server_name _;

    root /usr/share/nginx/html;
    index index.html;

    # Proxy API calls to the backend service
    location /api/ {
        proxy_pass ${BACKEND_URL};
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # SPA fallback: all unknown routes → index.html
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

> `${PORT}` is a Railway-injected env var (e.g. `3000`). Never hardcode `80`.
> `${BACKEND_URL}` must end with a trailing slash (e.g. `https://ziggytheque-backend.railway.app/`)
> so that `proxy_pass` correctly rewrites `/api/foo` → `https://...railway.app/api/foo`.

---

## Step 7 — railway.json (root): backend service Railway config

**File:** `railway.json` *(create at project root)*
**Why:** Railway reads this file from the service's root path to know how to build and deploy
the backend. Points to the root `Dockerfile`, targets the `prod` stage, passes `JWT_PASSPHRASE`
as a build arg sourced from the Railway environment variable of the same name.

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile",
    "dockerBuildTarget": "prod",
    "buildArgs": {
      "JWT_PASSPHRASE": "${{JWT_PASSPHRASE}}"
    }
  },
  "deploy": {
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

---

## Step 8 — front/railway.json: frontend service Railway config

**File:** `front/railway.json` *(create)*
**Why:** Railway's frontend service is configured to use `front/` as its root path. This file
tells Railway to build from `front/Dockerfile`. `VITE_API_BASE_URL` is passed as a build arg
but intentionally left empty — the frontend calls `/api` (relative), which nginx proxies.

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile",
    "buildArgs": {
      "VITE_API_BASE_URL": "${{VITE_API_BASE_URL}}"
    }
  },
  "deploy": {
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

> `VITE_API_BASE_URL` can be set to an empty string in Railway's frontend service env vars.
> Its presence as a build arg future-proofs the config if you ever want to call the backend
> directly (bypassing nginx proxy).

---

## Step 9 — worker/railway.json: Messenger consumer Railway service

**File:** `worker/railway.json` *(create — also creates the `worker/` directory)*
**Why:** In development the `worker` container in `docker-compose.yml` runs
`php bin/console messenger:consume async`. In production this becomes a separate Railway service
that uses the **same** `prod` image as the backend (Railway reuses the build cache) but overrides
the start command. Mirroring Ziggy: the worker service has its own `railway.json` so Railway can
manage it independently (separate deploy URL, logs, restart policy, env vars).

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "../Dockerfile",
    "dockerBuildTarget": "prod",
    "buildArgs": {
      "JWT_PASSPHRASE": "${{JWT_PASSPHRASE}}"
    }
  },
  "deploy": {
    "startCommand": "php bin/console messenger:consume async --time-limit=3600 -vv",
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 5
  }
}
```

> **Railway path note:** `dockerfilePath: "../Dockerfile"` is resolved relative to the service's
> root directory (`/worker`), meaning it points to the repo root `Dockerfile`. This works because
> Railway resolves the path from the repository root when the full path (`/Dockerfile`) is valid.
> If Railway rejects `../` paths, the fallback is: set the worker service root to `/` in Railway UI
> (same as backend) and configure `startCommand` exclusively from the Railway service settings panel
> — no `worker/railway.json` needed in that case.
>
> **On `--time-limit=3600`:** The consumer restarts every hour. Combined with Railway's
> `ON_FAILURE` restart policy this prevents slow memory leaks in long-lived consumers without
> risking message loss (the Doctrine transport re-queues unacked messages on clean exit).
>
> **On migrations:** The production `docker-entrypoint.sh` runs `doctrine:migrations:migrate`
> before `exec "$@"`. The worker runs migrations on startup too. This is safe — Doctrine's
> migrations lock the `migration_versions` table; concurrent runs do nothing.

---

## No Database Migration

No schema change is introduced by this plan. All steps are infrastructure/configuration only.

No schema change is introduced by this plan. All steps are infrastructure/configuration only.

---

## QA Gates

Run every gate in order. **Do not skip any.** Fix failures before proceeding.

### 1. Deptrac architecture check (new gate)
```bash
docker compose exec back vendor/bin/deptrac analyse
```
Expected: `[OK] No violations` (or investigate and fix any reported violations).
If you see "uncovered" classes, add them to the appropriate layer in `deptrac.yaml`.

### 2. PHP Code Style
```bash
docker compose exec back composer phpcbf
docker compose exec back composer phpcs
```
Expected: exit code 0.

### 3. PHPStan
```bash
docker compose exec back composer phpstan
```
Expected: `[OK] No errors`.

### 4. PHPUnit
```bash
docker compose exec back composer test
```
Expected: all tests pass.

### 5. Migration status clean
```bash
docker compose exec back php bin/console doctrine:migrations:status
```
Expected: no pending migrations.

### 6. Frontend type check
```bash
docker compose exec app npm run type-check
```
Expected: no errors.

### 7. Frontend lint
```bash
docker compose exec app npm run lint:check
```
Expected: no errors.

### 8. Frontend tests
```bash
docker compose exec app npm run test
```
Expected: all tests pass.

### 9. Docker build smoke test (local)
```bash
# Build the production image locally (shared by both backend and worker)
docker build --target prod --build-arg JWT_PASSPHRASE=test_passphrase -t ziggytheque-backend:test .

# Verify the worker start command runs against the same image
docker run --rm ziggytheque-backend:test php bin/console messenger:consume --help

# Build the frontend image locally
docker build -t ziggytheque-frontend:test ./front

# Build the database image locally
docker build -t ziggytheque-db:test ./db
```
Expected: all four commands succeed without errors.

### 10. Railway deployment (manual — after all local gates pass)

**Prerequisites (one-time, done in Railway dashboard):**
1. Create Railway project `ziggytheque`
2. Add service **db**: connect GitHub repo, root path `/db`, Railway reads `db/railway.json` automatically
3. Add service **backend**: connect same repo, root path `/`, Railway reads `railway.json` automatically
4. Add service **worker**: connect same repo, root path `/worker`, Railway reads `worker/railway.json`
5. Add service **frontend**: connect same repo, root path `/front`, Railway reads `front/railway.json`

**Database service (automatic setup):**
- Railway will build db/Dockerfile and expose PostgreSQL on internal hostname `db:5432`
- Database initialized with: POSTGRES_USER=ziggy, POSTGRES_PASSWORD=ziggy, POSTGRES_DB=ziggytheque
- No additional env vars needed — other services reference it via `db:5432` hostname

**Backend service env vars to set in Railway:**
```
APP_ENV=prod
APP_SECRET=<generate with: openssl rand -hex 32>
APP_DEBUG=0
JWT_PASSPHRASE=<strong random passphrase>
GATE_PASSWORD=<your gate password>
GOOGLE_BOOKS_API_KEY=<your Google Books API key>
CORS_ALLOW_ORIGIN=^https://your-frontend.railway.app$
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MONITOR_USER=monitor
MONITOR_PASSWORD=<strong password>
DATABASE_URL=postgresql://ziggy:ziggy@db:5432/ziggytheque
```

**Worker service env vars to set in Railway:**
```
APP_ENV=prod
APP_SECRET=<same value as backend>
APP_DEBUG=0
JWT_PASSPHRASE=<same value as backend>
DATABASE_URL=postgresql://ziggy:ziggy@db:5432/ziggytheque
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```
> The worker does not need `GATE_PASSWORD`, `CORS_ALLOW_ORIGIN`, `GOOGLE_BOOKS_API_KEY`, or `MONITOR_*`
> — it only processes queue messages, not HTTP requests.

**Frontend service env vars to set in Railway:**
```
BACKEND_URL=https://your-backend.railway.app/
VITE_API_BASE_URL=
```

**Deploy:**
```bash
git push origin main
# Railway auto-builds all four services on push (backend + worker share the same build cache)
```

**Smoke test after deploy:**
1. Open `https://your-frontend.railway.app` → gate page loads
2. Enter gate password → redirected to `/dashboard`
3. Open `/add` → search for a manga → results appear (Google Books API works)
4. Open `https://your-backend.railway.app/api/stats` with `Authorization: Bearer <token>` → JSON response
5. Check Railway worker service logs → should show `[Messenger] Waiting for messages...`
6. Check Railway db service logs → should show PostgreSQL is running

---

## Execution Checklist

### Infrastructure files
- [ ] Step 1 — Create `back/deptrac.yaml`
- [ ] Step 2 — Update `Makefile` (add `deptrac` target, `logs-worker`, update `php-qa`)
- [ ] Step 3 — Create `back/docker-entrypoint.sh` (chmod +x)
- [ ] Step 4 — Create root `Dockerfile` (multi-stage prod)
- [ ] Step 5 — Create `front/Dockerfile`
- [ ] Step 6 — Create `front/nginx.conf.template`
- [ ] Step 7 — Create `db/Dockerfile` (PostgreSQL 17 Alpine)
- [ ] Step 8 — Create `railway.json` (root — backend)
- [ ] Step 9 — Create `front/railway.json` (frontend)
- [ ] Step 10 — Create `db/railway.json` (PostgreSQL service)
- [ ] Step 11 — Create `worker/railway.json` (Messenger consumer)

### QA
- [ ] Deptrac passes (no violations)
- [ ] PHPCS passes
- [ ] PHPStan passes
- [ ] PHPUnit passes
- [ ] Migrations status clean
- [ ] Vue type-check passes
- [ ] ESLint passes
- [ ] Vitest passes
- [ ] `docker build` backend succeeds locally
- [ ] `docker build` frontend succeeds locally
- [ ] `docker build` db succeeds locally

### Railway (manual)
- [ ] Railway project created
- [ ] Database service created (root `/db`) + auto-initialized
- [ ] Backend service created (root `/`) + env vars set (including DATABASE_URL)
- [ ] Worker service created (root `/worker`) + env vars set (including DATABASE_URL)
- [ ] Frontend service created (root `/front`) + env vars set
- [ ] `git push` triggers successful deploy of all four services
- [ ] Backend logs show migrations ran
- [ ] Worker logs show `[Messenger] Waiting for messages...`
- [ ] Database logs show PostgreSQL is running
- [ ] Smoke test passes

### Git
- [ ] All changes on a single commit (`git commit --amend` if needed)
- [ ] PR created
