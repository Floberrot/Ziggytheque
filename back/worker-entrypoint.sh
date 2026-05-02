#!/bin/sh

# Generate JWT keypair if missing — the back service owns key generation;
# this is a fallback so the DI container compiles on cold starts.
if [ ! -f config/jwt/private.pem ]; then
    echo "[worker] Generating JWT keypair..."
    php bin/console lexik:jwt:generate-keypair --no-interaction \
        || echo "[worker] Warning: JWT keygen failed"
fi

echo "[worker] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod \
    || echo "[worker] Warning: cache warmup failed, continuing..."

echo "[worker] Starting Messenger consumer..."
exec "$@"
