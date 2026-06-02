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

# --- is_ignored ---------------------------------------------------------------
if is_ignored "APP_ENV";    then printf 'ok   - is_ignored APP_ENV\n';    else printf 'FAIL - is_ignored APP_ENV\n';    failures=$((failures + 1)); fi
if is_ignored "DATABASE_URL"; then printf 'ok   - is_ignored DATABASE_URL\n'; else printf 'FAIL - is_ignored DATABASE_URL\n'; failures=$((failures + 1)); fi
if is_ignored "GATE_PASSWORD"; then printf 'FAIL - GATE_PASSWORD must NOT be ignored\n'; failures=$((failures + 1)); else printf 'ok   - GATE_PASSWORD not ignored\n'; fi

# --- compute_missing: expected − ignored − already-present --------------------
expected=$'APP_SECRET\nGATE_PASSWORD\nDATABASE_URL\nGOOGLE_BOOKS_API_KEY\nAPP_ENV'
current=$'APP_SECRET\nRAILWAY_SERVICE_NAME\nDATABASE_URL'
# Missing = GATE_PASSWORD + GOOGLE_BOOKS_API_KEY (APP_SECRET present, DATABASE_URL
# present + ignored, APP_ENV ignored).
assert_eq "compute_missing returns only new, non-ignored keys" \
  $'GATE_PASSWORD\nGOOGLE_BOOKS_API_KEY' \
  "$(compute_missing "$expected" "$current")"

assert_eq "compute_missing is empty when nothing new" \
  "" \
  "$(compute_missing $'APP_SECRET\nAPP_ENV' $'APP_SECRET')"

# --- DRY_RUN end-to-end against the real .env files ---------------------------
# Exercises main()/sync_service wiring with no Railway calls; just asserts it
# succeeds and reports at least the known backend secrets as "would create".
out="$(cd "$SCRIPT_DIR/.." && DRY_RUN=1 GITHUB_ACTIONS= GITHUB_STEP_SUMMARY= bash scripts/railway-sync-env-keys.sh 2>&1)"
if printf '%s' "$out" | grep -q 'would create ziggytheque-back / GATE_PASSWORD='; then
  printf 'ok   - DRY_RUN flags GATE_PASSWORD for ziggytheque-back\n'
else
  printf 'FAIL - DRY_RUN did not flag GATE_PASSWORD\n%s\n' "$out"; failures=$((failures + 1))
fi
if printf '%s' "$out" | grep -q 'would create .* / DATABASE_URL='; then
  printf 'FAIL - DATABASE_URL must never be created\n'; failures=$((failures + 1))
else
  printf 'ok   - DRY_RUN never creates DATABASE_URL\n'
fi

echo
if [ "$failures" -eq 0 ]; then
  echo "All tests passed."
  exit 0
fi
echo "${failures} test(s) failed."
exit 1
