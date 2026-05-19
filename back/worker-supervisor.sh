#!/bin/sh
# Worker supervisor: waits for the DB, applies pending migrations, then loops
# messenger:consume with exponential backoff. Designed so the worker recovers
# from transient DB outages, missing schema, OOM, and timeouts without manual
# intervention. Used by both docker-compose (dev) and the production image.

set -u

CONSUMER_CMD="${WORKER_CONSUMER_CMD:-php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=256M --failure-limit=20 -v}"
DB_WAIT_TIMEOUT="${WORKER_DB_WAIT_TIMEOUT:-180}"
BACKOFF_INITIAL=1
BACKOFF_MAX=30

log() { echo "[worker $(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"; }

wait_for_db() {
    log "Waiting for database (timeout: ${DB_WAIT_TIMEOUT}s)..."
    waited=0
    # Use raw PDO so we don't pay the Symfony kernel boot cost on every retry.
    until php -r '
        $url = getenv("DATABASE_URL");
        if (!$url) { exit(1); }
        $parts = parse_url($url);
        if (!$parts || empty($parts["host"])) { exit(1); }
        $dsn = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s",
            $parts["host"],
            $parts["port"] ?? 5432,
            ltrim($parts["path"] ?? "", "/")
        );
        try {
            new PDO($dsn, $parts["user"] ?? null, $parts["pass"] ?? null, [PDO::ATTR_TIMEOUT => 2]);
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        if [ "$waited" -ge "$DB_WAIT_TIMEOUT" ]; then
            log "Database unreachable after ${DB_WAIT_TIMEOUT}s — exiting so the orchestrator can restart us."
            exit 1
        fi
        sleep 2
        waited=$((waited + 2))
    done
    log "Database is reachable."
}

run_migrations() {
    log "Applying Doctrine migrations (idempotent)..."
    if php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
        log "Migrations up to date."
    else
        log "Migration command failed — continuing anyway (web container may own migrations)."
    fi
}

ensure_jwt_keys() {
    if [ ! -f config/jwt/private.pem ]; then
        log "Generating JWT keypair (fallback)..."
        php bin/console lexik:jwt:generate-keypair --no-interaction \
            || log "Warning: JWT keygen failed."
    fi
}

warm_cache() {
    log "Warming Symfony cache..."
    php bin/console cache:warmup \
        || log "Warning: cache warmup failed, continuing..."
}

supervise() {
    backoff="$BACKOFF_INITIAL"
    while true; do
        log "Starting consumer: ${CONSUMER_CMD}"
        start_epoch=$(date +%s)
        # shellcheck disable=SC2086
        $CONSUMER_CMD
        exit_code=$?
        end_epoch=$(date +%s)
        runtime=$((end_epoch - start_epoch))

        if [ "$runtime" -ge 60 ]; then
            backoff="$BACKOFF_INITIAL"
        fi

        log "Consumer exited with code ${exit_code} after ${runtime}s. Restarting in ${backoff}s..."
        sleep "$backoff"

        backoff=$((backoff * 2))
        if [ "$backoff" -gt "$BACKOFF_MAX" ]; then
            backoff="$BACKOFF_MAX"
        fi
    done
}

cd /app || { log "Cannot cd to /app"; exit 1; }

wait_for_db
ensure_jwt_keys
run_migrations
warm_cache
supervise
