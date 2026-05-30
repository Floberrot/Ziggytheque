#!/usr/bin/env bash
#
# staging-db-sync.sh — Pull PRODUCTION data into the STAGING database, then
# anonymize it (RGPD). Runs from your machine using the PUBLIC (proxy) URLs of
# each Railway Postgres. Production is only ever READ (a single pg_dump).
#
# Config is read from environment variables. Store the values in an UNCOMMITTED
# file (see staging-db-sync.env.example). Load order:
#   --env-file PATH  >  back/scripts/staging-db-sync.env  >  already-exported env
#
# Required:
#   PROD_DATABASE_URL      public (proxy) URL of PROD Postgres    (read-only)
#   STAGING_DATABASE_URL   public (proxy) URL of STAGING Postgres (OVERWRITTEN)
#
# Usage:
#   back/scripts/staging-db-sync.sh [--yes] [--dump-only] [--env-file PATH]
#     --yes         skip the confirmation prompt
#     --dump-only   dump prod only (no restore, no anonymize) — for a smoke test
#     --env-file    path to the env file holding the URLs
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANON_SQL="$SCRIPT_DIR/anonymize-staging.sql"
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/staging-db-sync.env}"

ASSUME_YES=0
DUMP_ONLY=0
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes)      ASSUME_YES=1 ;;
    --dump-only)   DUMP_ONLY=1 ;;
    --env-file)    ENV_FILE="${2:?--env-file needs a path}"; shift ;;
    -h|--help)     sed -n '2,22p' "$0"; exit 0 ;;
    *)             echo "Unknown argument: $1" >&2; exit 2 ;;
  esac
  shift
done

die()  { echo "ERROR: $*" >&2; exit 1; }
need() { command -v "$1" >/dev/null 2>&1 || die "'$1' not found in PATH"; }
# Hide the password when printing a connection URL.
mask() { printf '%s' "$1" | sed -E 's#(://[^:/@]+):[^@]*@#\1:***@#'; }

# Load the uncommitted env file if present.
if [ -f "$ENV_FILE" ]; then
  echo "[staging-db-sync] Loading config from $ENV_FILE"
  set -a; . "$ENV_FILE"; set +a
fi

need pg_dump; need pg_restore; need psql
[ -f "$ANON_SQL" ] || die "anonymize SQL not found: $ANON_SQL"
: "${PROD_DATABASE_URL:?set PROD_DATABASE_URL (prod public proxy URL)}"
: "${STAGING_DATABASE_URL:?set STAGING_DATABASE_URL (staging public proxy URL)}"

# Safety: never let the restore/anonymize target be the prod database.
[ "$PROD_DATABASE_URL" != "$STAGING_DATABASE_URL" ] \
  || die "PROD_DATABASE_URL == STAGING_DATABASE_URL — refusing (would wipe prod)."

# pg_dump major must be >= server major (Railway runs PostgreSQL 17).
DUMP_MAJOR="$(pg_dump --version | grep -oE '[0-9]+' | head -1 || echo 0)"
[ "${DUMP_MAJOR:-0}" -ge 17 ] \
  || echo "WARNING: pg_dump is v${DUMP_MAJOR} (<17). The server is PG17 — restore may fail. Install postgresql-client 17."

echo
echo "  PROD    (read-only dump) : $(mask "$PROD_DATABASE_URL")"
echo "  STAGING (OVERWRITTEN)    : $(mask "$STAGING_DATABASE_URL")"
echo "  Anonymize SQL            : $ANON_SQL"
[ "$DUMP_ONLY" -eq 1 ] && echo "  Mode                     : DUMP ONLY"
echo

if [ "$ASSUME_YES" -ne 1 ] && [ "$DUMP_ONLY" -ne 1 ]; then
  printf 'This OVERWRITES the STAGING database, then anonymizes it. Type "yes" to continue: '
  read -r reply
  [ "$reply" = "yes" ] || die "aborted."
fi

# Temp dump file — always shredded on exit (it contains real PII).
DUMP_FILE="$(mktemp "${TMPDIR:-/tmp}/ziggy-prod-dump.XXXXXX")"
cleanup() { shred -u "$DUMP_FILE" 2>/dev/null || rm -f "$DUMP_FILE"; }
trap cleanup EXIT INT TERM

echo "[staging-db-sync] 1/3 Dumping PROD (read-only)…"
pg_dump "$PROD_DATABASE_URL" --no-owner --no-privileges --format=custom --file="$DUMP_FILE"
echo "[staging-db-sync]      dump OK ($(du -h "$DUMP_FILE" | cut -f1))"

if [ "$DUMP_ONLY" -eq 1 ]; then
  echo "[staging-db-sync] dump-only mode — nothing written to staging. Done."
  exit 0
fi

echo "[staging-db-sync] 2/3 Restoring into STAGING…"
pg_restore --clean --if-exists --no-owner --no-privileges \
  --dbname="$STAGING_DATABASE_URL" "$DUMP_FILE"

echo "[staging-db-sync] 3/3 Anonymizing STAGING (RGPD)…"
psql "$STAGING_DATABASE_URL" -v ON_ERROR_STOP=1 -f "$ANON_SQL"

echo
echo "[staging-db-sync] ✔ Staging refreshed with anonymized prod data."
echo "[staging-db-sync]   All passwords are now unusable. Create a staging admin:"
echo "[staging-db-sync]     railway ssh --environment staging --service ziggytheque-back -- \\"
echo "[staging-db-sync]       php bin/console app:bootstrap-admin <email> <password> --display-name Staging --force"
