#!/bin/sh

echo "[entrypoint] Listening on port ${PORT:-80}"

if [ ! -f config/jwt/private.pem ]; then
    echo "[entrypoint] Generating JWT keypair (first run)..."
    php bin/console lexik:jwt:generate-keypair --no-interaction \
        || echo "[entrypoint] Warning: JWT keygen failed"
else
    echo "[entrypoint] JWT keypair already present, skipping generation."
fi

echo "[entrypoint] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod \
    || echo "[entrypoint] Warning: cache warmup failed, continuing..."

echo "[entrypoint] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod \
    || echo "[entrypoint] Warning: migrations failed, continuing..."

echo "[entrypoint] Starting FrankenPHP..."
exec "$@"
