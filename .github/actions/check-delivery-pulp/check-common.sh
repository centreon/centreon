#!/usr/bin/env bash
# Shared helpers for the check-delivery-pulp scripts (check-rpm.sh, check-deb.sh).
#
# The check never aborts mid-verification: every expected package is checked, its
# per-package result is recorded, the full table is rendered to the step summary,
# and only then is a non-zero status returned if anything failed. Source this
# file, load the expected packages, verify them, then call render_summary.

PULP_URL="${PULP_URL:-https://pulp-api.apps.centreon.com}"
PULP_CONTENT_URL="${PULP_CONTENT_URL:-https://pulp-content.apps.centreon.com}"
METADATA_TIMEOUT="${METADATA_TIMEOUT:-300}"
METADATA_INTERVAL="${METADATA_INTERVAL:-15}"

# accumulated per-package result rows and the aggregate failure flag
ROWS=()
PRESENT_OK=0
META_OK=0
TOTAL=0
FAILED=0

# record_row <filename> <arch> <present:bool> <in_metadata:bool> <fetchable:bool>
record_row() {
  local filename=$1 arch=$2 present=$3 in_meta=$4 fetchable=$5
  local p m f
  TOTAL=$((TOTAL + 1))
  if [[ "$present" == "true" ]]; then p="✅"; PRESENT_OK=$((PRESENT_OK + 1)); else p="❌"; FAILED=1; fi
  if [[ "$in_meta" == "true" ]]; then m="✅"; META_OK=$((META_OK + 1)); else m="❌"; FAILED=1; fi
  if [[ "$fetchable" == "true" ]]; then f="✅"; else f="❌"; FAILED=1; fi
  ROWS+=("| \`$filename\` | $arch | $p | $m | $f |")
  echo "[CHECK] $filename ($arch): present=$p metadata=$m fetchable=$f"
}

# render_summary — write the per-OS table to the step summary (and stdout), then
# return non-zero if any package failed any check. Called once, after all checks.
render_summary() {
  local title
  title="### ${DISTRIB:-?} — ${STABILITY:-?} (${CHECK_MODE:-delivery}, pulp)"

  {
    echo "$title"
    echo ""
    echo "| Package | Arch | On repository | In metadata | Fetchable |"
    echo "|---|---|---|---|---|"
    if ((${#ROWS[@]})); then
      printf '%s\n' "${ROWS[@]}"
    fi
    echo ""
    echo "Result: ${PRESENT_OK}/${TOTAL} present · ${META_OK}/${TOTAL} resolvable"
    if [[ "$FAILED" -ne 0 ]]; then
      echo ""
      echo "> ❌ Some packages are missing on the repository or unresolvable through metadata."
    fi
    echo ""
  } | tee -a "${GITHUB_STEP_SUMMARY:-/dev/stdout}"

  if [[ "$FAILED" -ne 0 ]]; then
    echo "::error::check-delivery-pulp: ${DISTRIB:-?} verification failed (see the table above)"
    return 1
  fi
  echo "[INFO] All ${TOTAL} package(s) verified on ${DISTRIB:-?}"
  return 0
}
