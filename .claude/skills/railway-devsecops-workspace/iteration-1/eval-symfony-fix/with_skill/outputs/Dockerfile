# ── Composer deps ─────────────────────────────────────────────────────────────
FROM composer:2.7 AS composer
WORKDIR /app
COPY composer.json composer.lock symfony.lock* ./
RUN composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --no-scripts

# ── Frontend assets (Webpack Encore) ─────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json yarn.lock* ./
RUN npm ci
COPY . .
RUN npm run build

# ── Runtime ───────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine3.20 AS runner

RUN apk add --no-cache \
      nginx supervisor \
      postgresql-dev icu-dev \
    && docker-php-ext-install \
         pdo pdo_pgsql intl opcache \
    && rm -rf /tmp/*

# Non-root user (fixes: was running as root)
RUN addgroup -S www && adduser -S www -G www

WORKDIR /var/www/html

COPY --from=composer --chown=www:www /app/vendor ./vendor
COPY --from=assets   --chown=www:www /app/public/build ./public/build
COPY --chown=www:www . .

# NEVER copy .env into the image — secrets come from Railway env vars at runtime
# (removed: COPY .env /app/.env)

RUN APP_ENV=prod php bin/console cache:warmup || true

COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www:www var/

USER www
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD wget -qO- http://localhost:${PORT:-8080}/ || exit 1

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
