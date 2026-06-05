#!/usr/bin/env bash
#
# railway-sync-env-keys.sh
# ------------------------
# During a deploy, create a PLACEHOLDER Railway variable for every env var the
# app declares in its .env files but that is not yet present in the target
# Railway environment. The only manual step left is then overwriting the
# placeholder with the real value in the Railway dashboard ("j'ai juste a les
# remplir").
#
# The Railway CLI refuses an empty value ("Invalid variable format: KEY="), so
# placeholders are created with a visible sentinel value (default: CHANGEME,
# override with PLACEHOLDER_VALUE) that you replace.
#
# Design guarantees (intentionally non-destructive and deploy-safe):
#   * Only ever CREATES keys that are MISSING. It never edits or deletes an
#     existing variable, so a value you already set — or a Railway reference such
#     as ${{Postgres.DATABASE_URL}} — is never clobbered.
#   * New keys are created with --skip-deploys (when the CLI supports it) so the
#     creation itself does not trigger an extra deploy; the regular `railway up`
#     that runs right after picks them up.
#   * It NEVER aborts the deploy: a failed Railway call is reported and skipped,
#     and the script always exits 0.
#
# Source of truth = the committed .env files. `back/.env` is the canonical list
# of backend vars (its defaults are live in prod: the image runs `composer
# install` without `dump-env` and ships `back/.env`); `front/.env` for the SPA.
# Keys that must NOT be managed on Railway are skipped via IGNORE_KEYS /
# IGNORE_PATTERNS below — build-time vars, image-generated key paths,
# Railway-provided references, and vars whose committed default is already the
# production value (creating a placeholder for those would shadow a working
# default and break the app).
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

# Value given to newly created placeholder variables (the CLI cannot set empty).
PLACEHOLDER_VALUE="${PLACEHOLDER_VALUE:-CHANGEME}"

# Map each Railway service to the .env file that declares its variables.
# back and worker share the same backend contract (docker-compose proves it:
# the worker boots the same image with the same env as `back`).
SERVICE_ENV_FILES=$(cat <<'MAP'
ziggytheque-back:back/.env
ziggytheque-worker:back/.env
ziggytheque-front:front/.env
MAP
)

# Exact keys this script must NEVER create on Railway. When you add a var to
# back/.env whose committed default is ALSO the production value (a non-secret,
# non per-environment default), add its key here (or rely on IGNORE_PATTERNS).
IGNORE_KEYS=$(cat <<'KEYS'
APP_ENV
APP_DEBUG
JWT_SECRET_KEY
JWT_PUBLIC_KEY
JWT_TTL
DATABASE_URL
MESSENGER_TRANSPORT_DSN
VITE_API_BASE_URL
VITE_EXTERNAL_API_URL
OPENLIBRARY_USER_AGENT
EBAY_OAUTH_URL
KEYS
)

# Glob patterns of keys to ignore. `*_BASE_URL` covers public API base URLs
# (MANGADEX_BASE_URL, OPEN_LIBRARY_COVERS_BASE_URL, BNF_BASE_URL, ...) whose
# committed .env default IS the production value — a placeholder must never
# shadow them. This intentionally does NOT match MERCURE_*_URL, DATABASE_URL or
# DISCORD_WEBHOOK_URL (per-environment / secret → managed or handled explicitly).
IGNORE_PATTERNS="*_BASE_URL"

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

# is_ignored <key> — succeed if the key is in IGNORE_KEYS or matches IGNORE_PATTERNS.
is_ignored() {
  local key="$1" pattern
  printf '%s\n' "$IGNORE_KEYS" | grep -qxF "$key" && return 0
  for pattern in $IGNORE_PATTERNS; do
    # shellcheck disable=SC2254  # $pattern is an intentional glob
    case "$key" in
      $pattern) return 0 ;;
    esac
  done
  return 1
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
  return 0
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

# set_placeholder <service> <key> — create the key with the sentinel value.
# Returns non-zero (without aborting the script) if the Railway call fails.
set_placeholder() {
  local service="$1" key="$2" out
  if [ -n "${DRY_RUN:-}" ]; then
    log "DRY_RUN: would set ${service} / ${key}=${PLACEHOLDER_VALUE}"
    return 0
  fi
  if out=$(railway variables --service "$service" "${ENV_ARGS[@]}" \
            --set "${key}=${PLACEHOLDER_VALUE}" "${SKIP_DEPLOYS_ARGS[@]}" 2>&1); then
    return 0
  fi
  warn "${service}: could not set ${key} — ${out}"
  return 1
}

# sync_service <service> <env-file>
CREATED_TOTAL=0
FAILED_TOTAL=0
sync_service() {
  local service="$1" env_file="$2"
  local expected current missing key

  expected=$(extract_keys "$env_file" || true)
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

  missing=$(compute_missing "$expected" "$current" || true)
  if [ -z "$missing" ]; then
    log "${service}: up to date — no new env vars to create."
    return 0
  fi

  log "${service}: creating placeholders (value: ${PLACEHOLDER_VALUE}) for new env vars:"
  summary "### ${service}"
  while IFS= read -r key; do
    [ -n "$key" ] || continue
    if set_placeholder "$service" "$key"; then
      log "  + ${key}=${PLACEHOLDER_VALUE}"
      summary "- \`${key}\` → \`${PLACEHOLDER_VALUE}\` (overwrite with the real value in Railway)"
      CREATED_TOTAL=$((CREATED_TOTAL + 1))
    else
      summary "- \`${key}\` — ⚠️ creation failed (see logs)"
      FAILED_TOTAL=$((FAILED_TOTAL + 1))
    fi
  done <<EOF
$missing
EOF
  return 0
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
    sync_service "$service" "$env_file" || true
  done <<EOF
$SERVICE_ENV_FILES
EOF

  if [ "$CREATED_TOTAL" -gt 0 ]; then
    notice "Created ${CREATED_TOTAL} placeholder Railway variable(s) with value '${PLACEHOLDER_VALUE}'. Overwrite them with the real values in the Railway dashboard."
    log "Done — created ${CREATED_TOTAL} placeholder(s) (value: ${PLACEHOLDER_VALUE}). Overwrite them in Railway."
  else
    summary "_No new variables — every declared env var already exists._"
    log "Done — no new variables to create."
  fi
  if [ "$FAILED_TOTAL" -gt 0 ]; then
    warn "${FAILED_TOTAL} variable(s) could not be created — see logs above. Not failing the deploy."
  fi

  # Never block the deploy.
  exit 0
}

# Only run when executed directly, so tests can source the pure helpers.
if [ "${BASH_SOURCE[0]:-$0}" = "$0" ]; then
  main "$@"
fi
