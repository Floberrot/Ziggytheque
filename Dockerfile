# ── Stage 1: Frontend build ────────────────────────────────────────────────────
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

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Stage 3: PHP application code (shared by server and worker) ───────────────
FROM base AS app

ENV APP_ENV=prod
ENV APP_DEBUG=0
# FrankenPHP runs as root; without this Composer silently disables all plugins,
# including symfony/runtime, so vendor/autoload_runtime.php is never generated.
ENV COMPOSER_ALLOW_SUPERUSER=1
# PORT is injected by Railway at runtime; Caddyfile binds to :{$PORT:80}

COPY back/ .

RUN composer install \
    --no-dev \
    --no-interaction && \
    composer dump-autoload \
    --optimize \
    --classmap-authoritative

# ── Stage 4: Production server (app + SPA + FrankenPHP/Caddy) ─────────────────
FROM app AS prod

# Copy built Vue SPA into Symfony public directory (served by FrankenPHP as static files)
COPY --from=frontend /app/dist /app/public/spa

COPY back/Caddyfile /etc/caddy/Caddyfile
COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

# ── Stage 5: Messenger worker (no SPA, no Caddy — pure PHP consumer) ──────────
FROM app AS worker

COPY back/worker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "bin/console", "messenger:consume", "async", "scheduler_default", \
     "--time-limit=3600", "--memory-limit=128M", "-vv"]
