#!/bin/sh
set -e

echo "[entrypoint] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod

echo "[entrypoint] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[entrypoint] Starting FrankenPHP..."
exec "$@"
