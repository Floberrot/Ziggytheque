#!/usr/bin/env bash
#
# Unit tests for the pure helpers in railway-sync-env-keys.sh.
# No Railway calls are made — the script is sourced and its functions exercised
# against in-memory fixtures. Run: ./scripts/railway-sync-env-keys.test.sh
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
source "$SCRIPT_DIR/railway-sync-env-keys.sh"
# The sourced script enables `set -e`; restore lenient mode so every assertion runs.
set +e

failures=0
assert_eq() {
  local label="$1" expected="$2" actual="$3"
  if [ "$expected" = "$actual" ]; then
    printf 'ok   - %s\n' "$label"
  else
    printf 'FAIL - %s\n      expected: [%s]\n      actual:   [%s]\n' "$label" "$expected" "$actual"
    failures=$((failures + 1))
  fi
}
assert_true()  { if "$@"; then printf 'ok   - %s\n' "$*"; else printf 'FAIL - expected success: %s\n' "$*"; failures=$((failures + 1)); fi; }
assert_false() { if "$@"; then printf 'FAIL - expected failure: %s\n' "$*"; failures=$((failures + 1)); else printf 'ok   - ! %s\n' "$*"; fi; }

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

# --- extract_keys: parses keys, skips comments / markers / blanks --------------
cat > "$tmp" <<'ENVFILE'
###> a-bundle ###
APP_SECRET=change_me
# a comment
  GATE_PASSWORD=ziggy123

DATABASE_URL="postgres://u:p@h/db?x=1&y=2"
###< a-bundle ###
ENVFILE
assert_eq "extract_keys parses keys and ignores comments/blanks" \
  $'APP_SECRET\nDATABASE_URL\nGATE_PASSWORD' \
  "$(extract_keys "$tmp")"

assert_eq "extract_keys on a missing file is empty" "" "$(extract_keys /no/such/file)"

# --- is_ignored: exact list + glob patterns -----------------------------------
assert_true  is_ignored "APP_ENV"
assert_true  is_ignored "DATABASE_URL"
# *_BASE_URL pattern: public API base URLs must be ignored (their committed
# default IS the prod value) — this is the BNF_BASE_URL regression.
assert_true  is_ignored "BNF_BASE_URL"
assert_true  is_ignored "MANGADEX_BASE_URL"
assert_true  is_ignored "OPEN_LIBRARY_COVERS_BASE_URL"
# Secrets / per-env vars must NOT be ignored.
assert_false is_ignored "GATE_PASSWORD"
assert_false is_ignored "MERCURE_PUBLIC_URL"
assert_false is_ignored "DISCORD_WEBHOOK_URL"

# --- compute_missing: expected − ignored − already-present --------------------
expected=$'APP_SECRET\nGATE_PASSWORD\nDATABASE_URL\nGOOGLE_BOOKS_API_KEY\nAPP_ENV\nBNF_BASE_URL\nMANGADEX_BASE_URL'
current=$'APP_SECRET\nRAILWAY_SERVICE_NAME\nDATABASE_URL'
# Missing = GATE_PASSWORD + GOOGLE_BOOKS_API_KEY only (APP_SECRET present;
# DATABASE_URL present + ignored; APP_ENV ignored; *_BASE_URL ignored).
assert_eq "compute_missing returns only new, non-ignored keys" \
  $'GATE_PASSWORD\nGOOGLE_BOOKS_API_KEY' \
  "$(compute_missing "$expected" "$current")"

assert_eq "compute_missing is empty when nothing new" \
  "" \
  "$(compute_missing $'APP_SECRET\nAPP_ENV' $'APP_SECRET')"

# --- DRY_RUN end-to-end against the real .env files ---------------------------
# Exercises main()/sync_service wiring with no Railway calls; asserts it succeeds,
# uses the sentinel value, and never touches ignored keys.
out="$(cd "$SCRIPT_DIR/.." && DRY_RUN=1 GITHUB_ACTIONS= GITHUB_STEP_SUMMARY= bash scripts/railway-sync-env-keys.sh 2>&1)"
rc=$?
assert_eq "DRY_RUN exits 0" "0" "$rc"
if printf '%s' "$out" | grep -q 'would set ziggytheque-back / GATE_PASSWORD=CHANGEME'; then
  printf 'ok   - DRY_RUN sets GATE_PASSWORD to the sentinel value\n'
else
  printf 'FAIL - DRY_RUN did not set GATE_PASSWORD=CHANGEME\n%s\n' "$out"; failures=$((failures + 1))
fi
for forbidden in DATABASE_URL MANGADEX_BASE_URL OPEN_LIBRARY_COVERS_BASE_URL APP_ENV; do
  if printf '%s' "$out" | grep -q "would set .* / ${forbidden}="; then
    printf 'FAIL - ignored key %s must never be set\n' "$forbidden"; failures=$((failures + 1))
  else
    printf 'ok   - DRY_RUN never sets %s\n' "$forbidden"
  fi
done

# --- PLACEHOLDER_VALUE override -----------------------------------------------
out2="$(cd "$SCRIPT_DIR/.." && DRY_RUN=1 PLACEHOLDER_VALUE=__FILL__ GITHUB_ACTIONS= GITHUB_STEP_SUMMARY= bash scripts/railway-sync-env-keys.sh 2>&1)"
if printf '%s' "$out2" | grep -q 'GATE_PASSWORD=__FILL__'; then
  printf 'ok   - PLACEHOLDER_VALUE override is honored\n'
else
  printf 'FAIL - PLACEHOLDER_VALUE override not honored\n'; failures=$((failures + 1))
fi

echo
if [ "$failures" -eq 0 ]; then
  echo "All tests passed."
  exit 0
fi
echo "${failures} test(s) failed."
exit 1
