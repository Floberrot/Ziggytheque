#!/usr/bin/env bash
#
# railway-sync-env-keys.sh
# ------------------------
# During a deploy, create an EMPTY placeholder Railway variable for every env var
# the app declares in its .env files but that is not yet present in the target
# Railway environment. The only manual step left is then typing the value in the
# Railway dashboard ("j'ai juste a les remplir").
#
# Design guarantees (intentionally non-destructive):
#   * Only ever CREATES keys that are MISSING. It never edits or deletes an
#     existing variable, so a value you already set — or a Railway reference such
#     as ${{Postgres.DATABASE_URL}} — is never clobbered.
#   * New keys are created with --skip-deploys (when the CLI supports it) so the
#     creation itself does not trigger an extra, empty-valued deploy. The regular
#     `railway up` that runs right after picks the new keys up.
#   * Always exits 0 so it can never block a deploy. Newly created keys are
#     reported as GitHub Actions notices and in the job step summary.
#
# Source of truth = the committed .env files. Symfony's `back/.env` is the
# canonical list of backend vars (its defaults are live in prod: the image runs
# `composer install` without `dump-env` and ships `back/.env`); `front/.env` is
# the SPA's list. Keys that must NOT be managed on Railway are listed in
# IGNORE_KEYS below — build-time vars, image-generated key paths, Railway-provided
# references, and vars whose committed default is already the production value
# (creating those empty would shadow a working default).
#
# Usage:
#   RAILWAY_TOKEN=... [RAILWAY_PROJECT_ID=...] ./scripts/railway-sync-env-keys.sh [environment-id]
#   # environment-id defaults to $RAILWAY_ENVIRONMENT_ID; if empty, the Railway
#   # CLI falls back to the token's default environment (matches `railway up`).
#
# Dry run (no Railway calls — prints what would be created against a fake empty env):
#   DRY_RUN=1 ./scripts/railway-sync-env-keys.sh
#
set -euo pipefail

# --- Configuration ------------------------------------------------------------

# Map each Railway service to the .env file that declares its variables.
# back and worker share the same backend contract (docker-compose proves it:
# the worker boots the same image with the same env as `back`).
SERVICE_ENV_FILES=$(cat <<'MAP'
ziggytheque-back:back/.env
ziggytheque-worker:back/.env
ziggytheque-front:front/.env
MAP
)

# Keys this script must NEVER create on Railway. When you add a var to back/.env
# whose committed default is ALSO the production value (a non-secret, non
# per-environment default), add its key here so it is not shadowed by an empty
# Railway variable.
IGNORE_KEYS=$(cat <<'KEYS'
APP_ENV
APP_DEBUG
JWT_SECRET_KEY
JWT_PUBLIC_KEY
JWT_TTL
DATABASE_URL
MESSENGER_TRANSPORT_DSN
MANGADEX_BASE_URL
OPEN_LIBRARY_COVERS_BASE_URL
VITE_API_BASE_URL
VITE_EXTERNAL_API_URL
KEYS
)

# --- Logging helpers ----------------------------------------------------------

log()  { printf '[sync-env] %s\n' "$*"; }
warn() { printf '[sync-env] WARNING: %s\n' "$*" >&2; }

# Emit a GitHub Actions notice (no-op locally) and append a line to the job summary.
notice()  { [ -n "${GITHUB_ACTIONS:-}" ] && printf '::notice::%s\n' "$*" || true; }
summary() { [ -n "${GITHUB_STEP_SUMMARY:-}" ] && printf '%s\n' "$*" >> "$GITHUB_STEP_SUMMARY" || true; }

# --- Pure helpers (unit-testable by sourcing this file) -----------------------

# extract_keys <env-file> — print the variable keys declared in an .env file,
# one per line, ignoring comments and blank lines.
extract_keys() {
  local file="$1"
  [ -f "$file" ] || return 0
  { grep -E '^[[:space:]]*[A-Za-z_][A-Za-z0-9_]*=' "$file" || true; } \
    | sed -E 's/^[[:space:]]*//; s/=.*$//' \
    | sort -u
}

# is_ignored <key> — succeed if the key is in IGNORE_KEYS.
is_ignored() {
  printf '%s\n' "$IGNORE_KEYS" | grep -qxF "$1"
}

# compute_missing <expected-newline-list> <current-newline-list>
# Print every expected key that is neither ignored nor already present.
compute_missing() {
  local expected="$1" current="$2" key
  while IFS= read -r key; do
    [ -n "$key" ] || continue
    is_ignored "$key" && continue
    printf '%s\n' "$current" | grep -qxF "$key" && continue
    printf '%s\n' "$key"
  done <<EOF
$expected
EOF
}

# --- Railway I/O --------------------------------------------------------------

# Built once in main(): the --environment flag (omitted when no id is given) and
# the --skip-deploys flag (only when the installed CLI supports it).
ENV_ARGS=()
SKIP_DEPLOYS_ARGS=()

# get_current_keys <service> — print the keys currently set on the service in the
# target environment. Returns non-zero if the variables cannot be read, so the
# caller can skip the service instead of mass-creating placeholders on a glitch.
get_current_keys() {
  local service="$1" out
  if ! out=$(railway variables --service "$service" "${ENV_ARGS[@]}" --json 2>/dev/null); then
    return 1
  fi
  printf '%s' "$out" | jq -r 'keys[]' 2>/dev/null
}

# create_empty_var <service> <key>
create_empty_var() {
  local service="$1" key="$2"
  if [ -n "${DRY_RUN:-}" ]; then
    log "DRY_RUN: would create ${service} / ${key}= (empty)"
    return 0
  fi
  railway variables --service "$service" "${ENV_ARGS[@]}" \
    --set "${key}=" "${SKIP_DEPLOYS_ARGS[@]}" >/dev/null
}

# sync_service <service> <env-file>
CREATED_TOTAL=0
sync_service() {
  local service="$1" env_file="$2"
  local expected current missing key

  expected=$(extract_keys "$env_file")
  if [ -z "$expected" ]; then
    log "${service}: no keys declared in ${env_file} — nothing to do."
    return 0
  fi

  if [ -n "${DRY_RUN:-}" ]; then
    current=""
  elif ! current=$(get_current_keys "$service"); then
    warn "${service}: could not read current Railway variables — skipping (nothing created)."
    return 0
  fi

  missing=$(compute_missing "$expected" "$current")
  if [ -z "$missing" ]; then
    log "${service}: up to date — no new env vars to create."
    return 0
  fi

  log "${service}: creating empty placeholders for new env vars:"
  summary "### ${service}"
  while IFS= read -r key; do
    [ -n "$key" ] || continue
    log "  + ${key}"
    create_empty_var "$service" "$key"
    summary "- \`${key}\` (empty — fill its value in Railway)"
    CREATED_TOTAL=$((CREATED_TOTAL + 1))
  done <<EOF
$missing
EOF
}

# --- Entry point --------------------------------------------------------------

main() {
  local environment_id="${1:-${RAILWAY_ENVIRONMENT_ID:-}}"

  if [ -z "${DRY_RUN:-}" ]; then
    command -v railway >/dev/null 2>&1 || { warn "railway CLI not found — skipping env sync."; exit 0; }
    command -v jq      >/dev/null 2>&1 || { warn "jq not found — skipping env sync.";          exit 0; }
    if [ -z "${RAILWAY_TOKEN:-}" ]; then
      warn "RAILWAY_TOKEN is not set — skipping env sync."
      exit 0
    fi

    if [ -n "$environment_id" ]; then
      ENV_ARGS=(--environment "$environment_id")
      log "Target environment: ${environment_id}"
    else
      log "No environment id given — using the Railway token's default environment."
    fi

    if railway variables --help 2>/dev/null | grep -q -- '--skip-deploys'; then
      SKIP_DEPLOYS_ARGS=(--skip-deploys)
    else
      warn "This Railway CLI has no --skip-deploys flag; variable creation may trigger a deploy."
    fi
  else
    log "DRY_RUN enabled — no Railway calls will be made."
  fi

  summary "## Railway env var sync"

  local line service env_file
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    service="${line%%:*}"
    env_file="${line#*:}"
    sync_service "$service" "$env_file"
  done <<EOF
$SERVICE_ENV_FILES
EOF

  if [ "$CREATED_TOTAL" -gt 0 ]; then
    notice "Created ${CREATED_TOTAL} empty Railway variable(s). Fill their values in the Railway dashboard."
    log "Done — created ${CREATED_TOTAL} empty placeholder(s). Fill them in Railway."
  else
    summary "_No new variables — every declared env var already exists._"
    log "Done — no new variables to create."
  fi

  # Never block the deploy.
  exit 0
}

# Only run when executed directly, so tests can source the pure helpers.
if [ "${BASH_SOURCE[0]:-$0}" = "$0" ]; then
  main "$@"
fi
