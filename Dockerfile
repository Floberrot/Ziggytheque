# The SPA is not built here: the frontend service (front/Dockerfile, nginx) serves
# it and proxies /api to this backend.

# ── Stage 1: PHP base ─────────────────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    apcu

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Production php.ini (no error display, …) plus OPcache and limits for this app.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY back/docker/php-prod.ini $PHP_INI_DIR/conf.d/zz-app-prod.ini

# ── Stage 2: PHP application code (shared by server and worker) ───────────────
FROM base AS app

ENV APP_ENV=prod
ENV APP_DEBUG=0
# FrankenPHP runs as root; without this Composer silently disables all plugins,
# including symfony/runtime, so vendor/autoload_runtime.php is never generated.
ENV COMPOSER_ALLOW_SUPERUSER=1
# PORT is injected by Railway at runtime; Caddyfile binds to :{$PORT:80}

COPY back/ .

# composer install also warms the prod cache (auto-scripts: cache:clear), so the
# container starts on a built cache; the entrypoint only refreshes it.
RUN composer install \
    --no-dev \
    --no-interaction && \
    composer dump-autoload \
    --optimize \
    --classmap-authoritative

# ── Stage 3: Messenger worker (no Caddy — pure PHP consumer) ──────────────────
FROM app AS worker

COPY back/worker-supervisor.sh /usr/local/bin/worker-supervisor.sh
RUN chmod +x /usr/local/bin/worker-supervisor.sh

ENTRYPOINT ["worker-supervisor.sh"]

# ── Stage 4: Production server (app + FrankenPHP/Caddy) — DEFAULT TARGET ──────
FROM app AS prod

COPY back/Caddyfile /etc/caddy/Caddyfile
COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
