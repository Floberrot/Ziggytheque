#!/bin/sh
set -e

# Generate JWT keypair if missing — needed for the DI container to compile
# (the back service owns key generation; this is a fallback for cold starts)
if [ ! -f config/jwt/private.pem ]; then
    echo "[worker] Generating JWT keypair..."
    php bin/console lexik:jwt:generate-keypair --no-interaction
fi

echo "[worker] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod

echo "[worker] Starting Messenger consumer..."
exec "$@"
