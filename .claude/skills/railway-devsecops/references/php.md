# PHP — Symfony Docker Reference

## Stack defaults (non-negotiable)

| | Value |
|---|---|
| **PHP version** | 8.4 |
| **Symfony version** | 8.x |
| **PHP server** | FrankenPHP (replaces php-fpm + nginx) |
| **Frontend** | Vue 3 + Vite + DaisyUI |
| **Node.js** | 22 (LTS) |

## Recommended base images

| Use case | Image |
|----------|-------|
| **Symfony + FrankenPHP** (only option) | `dunglas/frankenphp:php8.4-alpine` |
| Symfony (CLI server, dev only) | `php:8.4-cli-alpine` |

**FrankenPHP is the only supported PHP server** — it replaces php-fpm + nginx + supervisor
with a single process built on Caddy.

Pin to full patch: `dunglas/frankenphp:php8.4-alpine` (check hub.docker.com/r/dunglas/frankenphp/tags).

---

## Symfony + FrankenPHP (recommended for Symfony)

FrankenPHP is a modern PHP server built on Caddy. It replaces php-fpm + nginx + supervisor
with a single binary — simpler Dockerfile, better performance, native HTTP/3.

```dockerfile
# ── Frontend build (Vue 3 SPA + Vite) ────────────────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app
COPY front/package*.json ./
RUN npm ci
COPY front/ .
RUN npm run build
# Output: /app/public/build/

# ── PHP base ─────────────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS base
WORKDIR /app

# Use install-php-extensions (bundled with FrankenPHP image) — NOT docker-php-ext-install
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    apcu

# Composer binary from official image (pin to a specific version if needed)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Caddyfile config (PORT handling done here)
COPY back/docker/php/Caddyfile /etc/caddy/Caddyfile

# ── Production ────────────────────────────────────────────────────────────────
FROM base AS prod
ENV APP_ENV=prod APP_DEBUG=0

# Copy backend source
COPY back/ .
RUN composer install --no-dev --no-interaction --optimize-autoloader --classmap-authoritative

# Copy Vue/React built assets into Symfony public dir
COPY --from=frontend /app/public/build /app/public/build

# Entrypoint runs cache:warmup etc. at startup (APP_SECRET available at runtime, not build time)
COPY back/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
```

**back/docker/php/Caddyfile** — PORT binding for Railway:
```caddy
{
    # FrankenPHP config
    frankenphp
}

:{$PORT:80} {
    root * /app/public
    encode zstd br gzip

    php_server
}
```

**back/docker/php/docker-entrypoint.sh**:
```bash
#!/bin/sh
set -e
php bin/console cache:warmup --env=prod
exec "$@"
```

**Important FrankenPHP notes:**
- Use `install-php-extensions` (bundled helper) — **not** `docker-php-ext-install`
- FrankenPHP runs as `root` by default; adding a non-root user requires extra Caddy config.
  For Railway deployments this is usually acceptable, but note it in the security report as `[INFO]`.
- Pin `composer:2.7`, not `composer:latest`

### Worker mode (optional but recommended for performance)

Worker mode boots the app **once** and reuses the process across requests — much faster than
traditional PHP-FPM. Enable it via Railway env vars (no Dockerfile change needed):

```
FRANKENPHP_CONFIG=worker ./public/index.php 4
MAX_REQUESTS=500
APP_RUNTIME=Runtime\\FrankenPhpSymfony\\Runtime
```

- `worker ./public/index.php 4` — 4 persistent PHP workers (tune to your CPU count)
- `MAX_REQUESTS=500` — restart each worker after 500 requests to prevent memory leaks
- `APP_RUNTIME` — requires `composer require runtime/frankenphp-symfony` in the Symfony app

**Custom worker script** (if not using the Symfony runtime package):
```php
<?php
// public/index.php
ignore_user_abort(true);
require __DIR__.'/../vendor/autoload.php';

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$handler = static function () use ($kernel): void {
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
};

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);
for ($i = 0; !$maxRequests || $i < $maxRequests; ++$i) {
    if (!\frankenphp_handle_request($handler)) break;
    gc_collect_cycles(); // prevent memory leaks between requests
}
```

**Restart workers on Railway** (via Caddy admin API, if enabled):
```bash
curl -X POST http://localhost:2019/frankenphp/workers/restart
```

---

## Monorepo structure (back/ + front/)

All projects use this layout:
```
project-root/
├── back/          # Symfony 8 + PHP 8.4
├── front/         # Vue 3 + Vite + DaisyUI
├── Dockerfile     # production multi-stage (Railway)
├── Dockerfile.dev # dev image (FrankenPHP + Composer + PHP extensions)
└── docker-compose.yml  # 5 services: back, app, db, mailer, worker
```

- The production `Dockerfile` lives at the **project root** and references both subdirectories
- `COPY back/ .` and `COPY front/package*.json ./` use paths relative to the build context
- Run `docker build .` from the repo root (Railway does this automatically)
- `railway.json` at project root points to `"dockerfilePath": "Dockerfile"`

---

## Symfony Messenger worker on Railway

Every project ships a **worker** as a separate Railway service (same image, different start command):

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": { "builder": "DOCKERFILE", "dockerfilePath": "Dockerfile" },
  "deploy": {
    "startCommand": "php bin/console messenger:consume async failed --time-limit=3600 --memory-limit=128M -vv",
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

The worker always has `zenstruck/messenger-monitor-bundle` for the Horizon-like dashboard — see `docker-gotchas.md` section 9 for full setup.

---

## Railway PORT note

FrankenPHP: configure Caddyfile to `:{$PORT:80}`.
Railway routes external traffic → `$PORT` inside the container automatically.

**Important**: `APP_SECRET` / `DATABASE_URL` must be set as Railway env vars — never bake them into the image.

---

## .dockerignore for PHP / Symfony

```
vendor/
node_modules/
.env
.env.*
*.env
var/cache/
var/log/
public/build/     # built inside Docker via frontend stage
phpunit.xml
phpunit.xml.dist
tests/
*.test.php
.phpunit.result.cache
phpstan.neon
.php-cs-fixer*
```
