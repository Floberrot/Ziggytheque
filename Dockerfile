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
# Composer runs as root at build time; without this it silently disables all plugins,
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

# The containers run as the unprivileged www-data user. The code stays root-owned
# (read-only to the app); only what the app writes at runtime is handed over:
# var/ (cache warmed at start) and config/jwt (keys generated on first start).
RUN mkdir -p var config/jwt && \
    chown -R www-data:www-data var config/jwt

# ── Stage 3: Messenger worker (no Caddy — pure PHP consumer) ──────────────────
FROM app AS worker

COPY back/worker-supervisor.sh /usr/local/bin/worker-supervisor.sh
RUN chmod +x /usr/local/bin/worker-supervisor.sh

USER www-data

ENTRYPOINT ["worker-supervisor.sh"]

# ── Stage 4: Production server (app + FrankenPHP/Caddy) — DEFAULT TARGET ──────
FROM app AS prod

COPY back/Caddyfile /etc/caddy/Caddyfile
COPY back/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Without root, Caddy still needs to write its config (autosave) and data (the Mercure
# hub's database) dirs, and to bind port 80 when Railway's PORT is unset: the binary
# carries CAP_NET_BIND_SERVICE (FrankenPHP's documented non-root setup).
ENV XDG_CONFIG_HOME=/config
ENV XDG_DATA_HOME=/data
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp && \
    mkdir -p /config/caddy /data/caddy && \
    chown -R www-data:www-data /config/caddy /data/caddy

USER www-data

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
