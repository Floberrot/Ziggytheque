# ── Composer install stage ────────────────────────────────────────────────────
FROM composer:2.7 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --no-scripts

# ── Runtime stage ─────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine3.20 AS runner

RUN apk add --no-cache \
      nginx supervisor \
      postgresql-dev \
      icu-dev \
    && docker-php-ext-install \
         pdo pdo_pgsql \
         intl opcache pcntl bcmath \
    && rm -rf /tmp/*

RUN addgroup -S www && adduser -S www -G www

WORKDIR /var/www/html

COPY --from=composer --chown=www:www /app/vendor ./vendor
COPY --chown=www:www . .

# Remove .env if accidentally present — secrets come from Railway env vars
RUN rm -f .env

RUN php artisan config:cache \
    && php artisan route:cache

COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/php-fpm.conf     /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www:www storage bootstrap/cache

USER www
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD wget -qO- http://localhost:${PORT:-8080}/up || exit 1

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
