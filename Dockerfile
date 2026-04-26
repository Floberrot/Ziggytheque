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

ENV APP_ENV=prod
ENV APP_DEBUG=0
# SERVER_NAME is set at runtime in docker-entrypoint.sh using Railway's PORT env var

COPY back/ .

RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts

# Copy built Vue SPA into Symfony public directory (served by FrankenPHP as static files)
COPY --from=frontend /app/dist /app/public/spa

COPY back/Caddyfile /etc/caddy/Caddyfile
COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE ${PORT:-80}

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

# ── Stage 4: Worker ───────────────────────────────────────────────────────────
FROM prod AS worker

COPY back/worker-entrypoint.sh /usr/local/bin/worker-entrypoint.sh
RUN chmod +x /usr/local/bin/worker-entrypoint.sh

ENTRYPOINT ["worker-entrypoint.sh"]
CMD ["php", "bin/console", "messenger:consume", "async", "scheduler_default", "--time-limit=3600", "-vv"]
