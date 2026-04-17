# Docker Gotchas — Known Errors to Never Repeat

Read this file whenever writing a `Dockerfile` or `docker-compose.yml`. Each entry is a
real bug that was hit and fixed. Apply the correct pattern from the start.

---

## 1. FrankenPHP image tag format

**Error:**
```
docker.io/dunglas/frankenphp:latest-php8.4-alpine: not found
```

**Wrong:**
```dockerfile
FROM dunglas/frankenphp:latest-php8.4-alpine
```

**Correct:**
```dockerfile
FROM dunglas/frankenphp:php8.4-alpine
```

**Rule:** FrankenPHP tags are `php{VERSION}-alpine` or `php{VERSION}` — never prefix with `latest-`.
Check https://hub.docker.com/r/dunglas/frankenphp/tags for the exact list.

---

## 2. COPY composer.lock fails on a fresh repo

**Error:**
```
failed to compute cache key: "/back/composer.lock": not found
```

**Cause:** The `Dockerfile` tries to `COPY back/composer.lock` but the file doesn't exist
because `composer install` was never run locally (fresh clone or new project).

**Wrong pattern** — using the production Dockerfile for local dev:
```yaml
# docker-compose.yml
api:
  build:
    context: .
    dockerfile: Dockerfile   # requires composer.lock to exist
```

**Correct pattern** — use the base image directly in `docker-compose.yml` for dev:
```yaml
# docker-compose.yml
api:
  image: dunglas/frankenphp:php8.4-alpine
  working_dir: /app
  command: >
    sh -c "
      composer install --no-interaction --prefer-dist &&
      php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration &&
      frankenphp php-server --listen :8000 --root /app/public
    "
  volumes:
    - ./back:/app
    - vendor_cache:/app/vendor   # named volume keeps vendor between restarts

volumes:
  vendor_cache:
```

**Rule:** `docker-compose.yml` (dev) never builds the production `Dockerfile`. It mounts
the source and installs dependencies at container startup. The `Dockerfile` is for CI/Railway
only, where `composer.lock` is always committed.

**Corollary:** Always commit `composer.lock` and `package-lock.json` to the repository.
The `Dockerfile` may then safely `COPY` them for layer-cache efficiency in production builds.

---

## 3. Composer not found in FrankenPHP base image

**Error:**
```
sh: composer: not found
```

**Cause:** `dunglas/frankenphp:php8.4-alpine` ships PHP and FrankenPHP but **not Composer**.
Using `image:` directly in `docker-compose.yml` means Composer is never installed.

**Wrong pattern** — expecting Composer to be present in the base image:
```yaml
api:
  image: dunglas/frankenphp:php8.4-alpine
  command: sh -c "composer install && ..."   # ❌ composer not found
```

**Correct pattern** — use a `Dockerfile.dev` that extends the base and copies Composer:
```dockerfile
# Dockerfile.dev
FROM dunglas/frankenphp:php8.4-alpine

RUN install-php-extensions pdo_pgsql intl mbstring opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

```yaml
# docker-compose.yml
api:
  build:
    context: .
    dockerfile: Dockerfile.dev   # ✅ built once, cached, Composer available
  command: >
    sh -c "
      composer install --no-interaction --prefer-dist &&
      php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration &&
      php bin/console messenger:setup-transports --no-interaction &&
      frankenphp php-server --listen :8000 --root /app/public
    "
  healthcheck:
    test: ["CMD-SHELL", "curl -sf http://localhost:8000/messenger > /dev/null || exit 1"]
    interval: 10s
    timeout: 5s
    retries: 10
    start_period: 30s
  volumes:
    - ./back:/app
    - vendor_cache:/app/vendor

worker:
  build:
    context: .
    dockerfile: Dockerfile.dev
  working_dir: /app
  command: >
    sh -c "
      composer install --no-interaction --prefer-dist &&
      php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration &&
      php bin/console messenger:consume async failed --time-limit=3600 --memory-limit=128M -vv
    "
  volumes:
    - ./back:/app
    - vendor_cache:/app/vendor
  environment:
    # same env vars as api service
    MESSENGER_TRANSPORT_DSN: doctrine://default?auto_setup=0
  restart: unless-stopped
  depends_on:
    db:
      condition: service_healthy
```

**Rule:** Any project using FrankenPHP in docker-compose needs a `Dockerfile.dev`.
This thin image (base + PHP extensions + Composer) is built once and cached by Docker —
it does not re-download on every `docker compose up`.

---

## 4. Vite proxy target uses `localhost` — breaks inside Docker

**Error:**
```
POST http://localhost:5173/api/auth/register → 500
```

**Cause:** `vite.config.ts` hard-codes the proxy target as `http://localhost:8000`. Inside
the `front` Docker container, `localhost` is the container itself — not the `api` service.
Vite's proxy runs server-side, so it never reaches the backend.

**Wrong:**
```ts
// vite.config.ts
proxy: {
  '/api': { target: 'http://localhost:8000' }
}
```

**Correct:** Make the target configurable via an env var with a safe local default:
```ts
// vite.config.ts
proxy: {
  '/api': {
    target: process.env.API_PROXY_TARGET ?? 'http://localhost:8000',
    changeOrigin: true,
  },
}
```

```yaml
# docker-compose.yml — front service
environment:
  API_PROXY_TARGET: http://api:8000   # Docker service name, not localhost
```

**Rule:** Never hard-code `localhost` in the Vite proxy target. Always use
`process.env.API_PROXY_TARGET ?? 'http://localhost:8000'` and set the env var
to the Docker service name in `docker-compose.yml`.

---

## 5. Missing env vars cause autowire failure at container start

**Error:**
```
Cannot autowire service "App\...\SomeHandler": argument "$someParam" of method
"__construct()" is type-hinted "string", you should configure its value explicitly.
```

**Cause:** A handler or service has a `string` constructor parameter bound via
`services.yaml` `_defaults.bind`, but the backing env var is not declared in
`docker-compose.yml`.

**Wrong:** env var present in `.env` but not forwarded to the container:
```yaml
# docker-compose.yml — missing FRONTEND_BASE_URL
environment:
  APP_ENV: dev
  DATABASE_URL: ...
```

**Correct:** every env var referenced in `services.yaml` bind section must be in the
container environment:
```yaml
environment:
  FRONTEND_BASE_URL: 'http://localhost:5173'
  MESSENGER_TRANSPORT_DSN: doctrine://default?auto_setup=0
```

**Rule:** when adding a `bind:` entry in `services.yaml` that reads from `%env(...)%`,
immediately add that var to **all three places**: `.env` (local default), `docker-compose.yml`
(dev value), and Railway secrets (production value).

---

## 6. Composer scripts run before application source is copied

**Error:**
```
Script cache:clear returned with error code 1
In FrameworkExtension.php: class "App\...\SomeEvent" not found.
```

**Cause:** In the production `Dockerfile`, `composer install` (which triggers
`post-install-cmd` → `cache:clear`) is run before `COPY back/ .`. The source files
don't exist yet so the DI container can't be built.

**Wrong:**
```dockerfile
COPY back/composer.json back/composer.lock ./
RUN composer install --no-dev --no-scripts ...   # scripts skipped here...

COPY back/ .

RUN composer dump-autoload --optimize --no-dev   # ...but still no cache:warmup
```

**Correct:** skip scripts during the dependency install step, then run
`dump-autoload` + `cache:warmup` after all source is present:
```dockerfile
COPY back/composer.json back/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY back/ .
COPY --from=frontend-builder /app/front/dist ./public/app

RUN composer dump-autoload --optimize --no-dev \
    && php bin/console cache:warmup --env=prod --no-debug
```

**Rule:** always use `--no-scripts` on the first `composer install` in a multi-stage
Dockerfile. Run `cache:warmup` explicitly after all `COPY` steps are done.

---

## 7. Postgres volume already exists — database never created

**Error:**
```
FATAL:  database "ziggytheque" does not exist
```
or more confusingly, the database name matches the `POSTGRES_USER` value:
```
FATAL:  database "ziggy" does not exist
```

**Cause:** Postgres only runs its init scripts (`POSTGRES_DB`, `POSTGRES_USER`,
`POSTGRES_PASSWORD`) on a **fresh, empty volume**. If the `pg_data` named volume
already exists from a previous run (different project, renamed DB, or before
`POSTGRES_DB` was set), Postgres starts against the old data and never creates
the expected database.

**Wrong:** trying to fix by recreating just the container:
```bash
docker compose down && docker compose up   # volume survives → same error
```

**Correct:** destroy the volume so Postgres reinitialises completely:
```bash
docker compose down -v   # -v removes named volumes (pg_data, vendor_cache, …)
docker compose up
```

**Rule:** whenever you see `FATAL: database "X" does not exist` on a fresh project
or after renaming the database, always run `docker compose down -v` first.
Warn the user that `-v` deletes all local data — fine for dev, never for prod.

---

## 8. Symfony Messenger doctrine:// transport → 500 on first request

**Error:**
```
No transport supports the given Messenger DSN.
Run "composer require symfony/doctrine-messenger" to install Doctrine transport.
```

**Cause:** `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` is a common default
in `.env`, but `symfony/doctrine-messenger` is not included in the standard Symfony skeleton.
The transport package is missing, so every command dispatched to the bus throws.

**Wrong:** relying on the default `.env` skeleton values without checking installed packages:
```bash
# composer.json has symfony/messenger but NOT symfony/doctrine-messenger
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0   # ❌ explodes at runtime
```

**Correct:**
1. Install the package:
```bash
composer require symfony/doctrine-messenger
```
2. Add `messenger:setup-transports` to the container startup command so the transport
   tables are created automatically on every `docker compose up`:
```yaml
# docker-compose.yml
command: >
  sh -c "
    composer install --no-interaction --prefer-dist &&
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration &&
    php bin/console messenger:setup-transports --no-interaction &&
    frankenphp php-server --listen :8000 --root /app/public
  "
```

**Rule:** whenever `MESSENGER_TRANSPORT_DSN=doctrine://` is used, `symfony/doctrine-messenger`
must be in `composer.json` and `messenger:setup-transports` must run at container startup.
`auto_setup=0` means Doctrine will never create the table itself — the console command is
the only way to initialise it.

---

## 9. Standard 5-container project setup (mandatory for every new project)

Every project MUST start with this `docker-compose.yml` structure. No exceptions.

```yaml
# docker-compose.yml
services:

  # ── Symfony 8 backend (FrankenPHP + PHP 8.4) ──────────────────────────────
  back:
    build:
      context: .
      dockerfile: Dockerfile.dev
    working_dir: /app
    command: >
      sh -c "
        composer install --no-interaction --prefer-dist &&
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration &&
        php bin/console messenger:setup-transports --no-interaction &&
        frankenphp php-server --listen :8000 --root /app/public
      "
    ports:
      - "8000:8000"
    volumes:
      - ./back:/app
      - vendor_cache:/app/vendor
    environment:
      APP_ENV: dev
      APP_SECRET: ${APP_SECRET:-dev_secret_change_me}
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-app}@db:5432/${POSTGRES_DB:-app}
      MESSENGER_TRANSPORT_DSN: doctrine://default?auto_setup=0
      MAILER_DSN: smtp://mailer:1025
      MONITOR_USER: ${MONITOR_USER:-monitor}
      MONITOR_PASSWORD: ${MONITOR_PASSWORD:-changeme_monitor_dev}
    depends_on:
      db:
        condition: service_healthy
      mailer:
        condition: service_started
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost:8000/ > /dev/null || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 30s

  # ── Vue 3 frontend (Vite dev server) ──────────────────────────────────────
  app:
    image: node:22-alpine
    working_dir: /app
    command: sh -c "npm install && npm run dev -- --host"
    ports:
      - "5173:5173"
    volumes:
      - ./front:/app
      - node_modules_cache:/app/node_modules
    environment:
      API_PROXY_TARGET: http://back:8000
    depends_on:
      back:
        condition: service_healthy

  # ── PostgreSQL database ────────────────────────────────────────────────────
  db:
    image: postgres:17-alpine
    environment:
      POSTGRES_USER: ${POSTGRES_USER:-app}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-app}
      POSTGRES_DB: ${POSTGRES_DB:-app}
    ports:
      - "5432:5432"
    volumes:
      - pg_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-app}"]
      interval: 5s
      timeout: 5s
      retries: 10

  # ── Mailpit (local email catcher) ─────────────────────────────────────────
  mailer:
    image: axllent/mailpit:latest
    ports:
      - "8025:8025"   # web UI
      - "1025:1025"   # SMTP

  # ── Symfony Messenger worker (async queue consumer) ───────────────────────
  worker:
    build:
      context: .
      dockerfile: Dockerfile.dev
    working_dir: /app
    command: >
      sh -c "
        composer install --no-interaction --prefer-dist &&
        php bin/console messenger:consume async failed --time-limit=3600 --memory-limit=128M -vv
      "
    volumes:
      - ./back:/app
      - vendor_cache:/app/vendor
    environment:
      APP_ENV: dev
      APP_SECRET: ${APP_SECRET:-dev_secret_change_me}
      DATABASE_URL: postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-app}@db:5432/${POSTGRES_DB:-app}
      MESSENGER_TRANSPORT_DSN: doctrine://default?auto_setup=0
      MAILER_DSN: smtp://mailer:1025
      MONITOR_USER: ${MONITOR_USER:-monitor}
      MONITOR_PASSWORD: ${MONITOR_PASSWORD:-changeme_monitor_dev}
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy

volumes:
  pg_data:
  vendor_cache:
  node_modules_cache:
```

**Service summary:**

| Service | URL | Purpose |
|---------|-----|---------|
| `back` | http://localhost:8000 | Symfony 8 API + FrankenPHP |
| `app` | http://localhost:5173 | Vue 3 + Vite dev server |
| `db` | localhost:5432 | PostgreSQL 17 |
| `mailer` | http://localhost:8025 | Mailpit web UI (email catcher) |
| `worker` | — | Symfony Messenger queue consumer |

**Messenger dashboard** (Horizon-equivalent): http://localhost:8000/messenger — see section 10 for setup.

**Required `.env` defaults** (committed):
```dotenv
APP_SECRET=dev_secret_change_me
POSTGRES_USER=app
POSTGRES_PASSWORD=app
POSTGRES_DB=app
MONITOR_USER=monitor
MONITOR_PASSWORD=changeme_monitor_dev
```

---

## 10. Symfony Messenger — worker container + monitor dashboard (standard setup)

Every Symfony project using Messenger must ship two things in `docker-compose.yml`:
1. A **`worker` service** that consumes queues continuously
2. A **`/messenger` dashboard** (via `zenstruck/messenger-monitor-bundle`) to inspect queue health

### Install once per project

```bash
composer require symfony/doctrine-messenger zenstruck/messenger-monitor-bundle
```

### ProcessedMessage entity (Shared/Infrastructure layer)

```php
// src/Shared/Infrastructure/Messenger/ProcessedMessage.php
namespace App\Shared\Infrastructure\Messenger;

use Doctrine\ORM\Mapping as ORM;
use Zenstruck\Messenger\Monitor\History\Model\ProcessedMessage as BaseProcessedMessage;

#[ORM\Entity(readOnly: true)]
#[ORM\Table('messenger_processed_messages')]
final class ProcessedMessage extends BaseProcessedMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function id(): ?int { return $this->id; }
}
```

### Dashboard controller — protected by ROLE_MONITOR

```php
// src/Shared/Infrastructure/Messenger/MessengerMonitorController.php
namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Zenstruck\Messenger\Monitor\Controller\MessengerMonitorController as BaseMessengerMonitorController;

#[Route('/messenger')]
#[IsGranted('ROLE_MONITOR')]
final class MessengerMonitorController extends BaseMessengerMonitorController {}
```

### security.yaml additions

Add alongside the existing `password_hashers` / `providers` / `firewalls`:

```yaml
security:
    password_hashers:
        # ...existing hashers...
        Symfony\Component\Security\Core\User\InMemoryUser:
            algorithm: plaintext   # password stored as plain env var

    providers:
        # ...existing providers...
        monitor_provider:
            memory:
                users:
                    '%env(MONITOR_USER)%':
                        password: '%env(MONITOR_PASSWORD)%'
                        roles: ['ROLE_MONITOR']

    firewalls:
        # ...other firewalls...
        monitor:
            pattern: ^/messenger
            provider: monitor_provider
            http_basic:
                realm: 'Messenger Monitor'

        # Add provider: app_user_provider to login + api firewalls
        # (required when multiple providers exist)
        login:
            provider: app_user_provider
            # ...rest unchanged...
        api:
            provider: app_user_provider
            # ...rest unchanged...

    access_control:
        - { path: ^/messenger, roles: ROLE_MONITOR }
        # ...existing rules...
```

### ExceptionMiddleware — must not swallow security exceptions

The global `ExceptionMiddleware` must let `AuthenticationException` and
`AccessDeniedException` pass through unchanged, or the HTTP Basic challenge
(401 + `WWW-Authenticate` header) will never reach the browser:

```php
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

public function onKernelException(ExceptionEvent $event): void
{
    $throwable = $event->getThrowable();

    if ($throwable instanceof AuthenticationException || $throwable instanceof AccessDeniedException) {
        return;   // let Symfony's security system handle these
    }
    // ...rest of handler
}
```

### docker-compose env vars (per-project credentials)

```yaml
# api + worker services — both need MONITOR_USER / MONITOR_PASSWORD
environment:
  MONITOR_USER: ${MONITOR_USER:-monitor}
  MONITOR_PASSWORD: ${MONITOR_PASSWORD:-changeme_monitor_dev}
```

Set a strong password per project in `.env` (dev) and in Railway secrets (prod).
The default `changeme_monitor_dev` is intentionally obvious — replace it.

### Bundle config

```yaml
# config/packages/zenstruck_messenger_monitor.yaml
zenstruck_messenger_monitor:
    storage:
        orm:
            entity_class: App\Shared\Infrastructure\Messenger\ProcessedMessage
```

### Register bundle in bundles.php

```php
Zenstruck\Messenger\Monitor\ZenstruckMessengerMonitorBundle::class => ['all' => true],
```

### messenger.yaml — keep async in dev, sync only in test

```yaml
when@test:
    framework:
        messenger:
            transports:
                async: 'in-memory://'
```

Do NOT add a `when@dev: sync://` override — the worker handles dev consumption.

### After setup: generate and run migration

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate --no-interaction
```

### Worker service restart behaviour

`restart: unless-stopped` + `--time-limit=3600` = worker exits cleanly after 1 hour
and Docker restarts it automatically. This prevents PHP memory leaks in long-running processes.

Dashboard is at **http://localhost:8000/messenger** — shows workers, transport queue counts,
and full processed-message history.
