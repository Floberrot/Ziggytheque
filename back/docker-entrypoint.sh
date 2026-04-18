#!/bin/sh
set -e

# FrankenPHP reads SERVER_NAME to determine the port. Railway assigns PORT
# dynamically, so override the Dockerfile default at runtime.
export SERVER_NAME="http://:${PORT:-80}"
echo "[entrypoint] Listening on port ${PORT:-80}"

if [ ! -f config/jwt/private.pem ]; then
    echo "[entrypoint] Generating JWT keypair (first run)..."
    php bin/console lexik:jwt:generate-keypair --no-interaction
else
    echo "[entrypoint] JWT keypair already present, skipping generation."
fi

echo "[entrypoint] Warming up Symfony cache..."
php bin/console cache:warmup --env=prod

echo "[entrypoint] Testing raw DB connection..."
php -r "
\$url = getenv('DATABASE_URL');
echo '[diag] DATABASE_URL (masked): ' . preg_replace('/:[^:@]+@/', ':***@', \$url) . PHP_EOL;
preg_match('#://([^:]+):([^@]+)@([^:/]+):(\d+)/([^?]+)#', \$url, \$m);
if (!isset(\$m[5])) { echo '[diag] Could not parse DATABASE_URL' . PHP_EOL; exit(1); }
echo '[diag] host=' . \$m[3] . ' port=' . \$m[4] . ' dbname=' . \$m[5] . ' user=' . \$m[1] . PHP_EOL;
echo '[diag] pass_len=' . strlen(\$m[2]) . PHP_EOL;
echo '[diag] pass_hex=' . bin2hex(substr(\$m[2], 0, 4)) . '...' . PHP_EOL;

// Try 1: private URL, no SSL
try {
    \$pdo = new PDO('pgsql:host='.\$m[3].';port='.\$m[4].';dbname='.\$m[5].';sslmode=disable', \$m[1], \$m[2]);
    echo '[diag] private+no-ssl: OK' . PHP_EOL;
} catch (Exception \$e) {
    echo '[diag] private+no-ssl FAILED: ' . \$e->getMessage() . PHP_EOL;
}

// Try 2: private URL, require SSL
try {
    \$pdo = new PDO('pgsql:host='.\$m[3].';port='.\$m[4].';dbname='.\$m[5].';sslmode=require', \$m[1], \$m[2]);
    echo '[diag] private+ssl-require: OK' . PHP_EOL;
} catch (Exception \$e) {
    echo '[diag] private+ssl-require FAILED: ' . \$e->getMessage() . PHP_EOL;
}

// Try 3: public proxy URL
\$pubUrl = getenv('DATABASE_PUBLIC_URL');
if (\$pubUrl) {
    preg_match('#://([^:]+):([^@]+)@([^:/]+):(\d+)/([^?]+)#', \$pubUrl, \$p);
    echo '[diag] public host=' . \$p[3] . ' port=' . \$p[4] . PHP_EOL;
    try {
        \$pdo = new PDO('pgsql:host='.\$p[3].';port='.\$p[4].';dbname='.\$p[5].';sslmode=require', \$p[1], \$p[2]);
        echo '[diag] public+ssl: OK' . PHP_EOL;
    } catch (Exception \$e) {
        echo '[diag] public+ssl FAILED: ' . \$e->getMessage() . PHP_EOL;
    }
} else {
    echo '[diag] DATABASE_PUBLIC_URL not set' . PHP_EOL;
}
"

echo "[entrypoint] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[entrypoint] Starting FrankenPHP..."
exec "$@"
