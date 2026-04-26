#!/bin/sh
set -e

echo "[worker] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod

echo "[worker] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[worker] Starting Messenger consumer..."
exec "$@"
