#!/usr/bin/env bash
set -euo pipefail

# Tests the run selection of sources-run-selection.sh on fixture API output, without the network.
# Run: bash .github/scripts/release/sources-run-selection-test.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source-path=SCRIPTDIR source=sources-run-selection.sh
source "$SCRIPT_DIR/sources-run-selection.sh"

REPO="centreon/centreon"
TAG="centreon-web-26.10.1"
FIXTURES="$(mktemp -d)"
trap 'rm -rf "$FIXTURES"' EXIT

# Jobs of each fixture run, keyed by run id; a run absent from the map has no job at all, and
# FAIL makes its jobs call fail like an API error would.
declare -A JOBS=()
DELIVER_OK='{"jobs":[{"name":"build"},{"name":"deliver-sources","status":"completed","conclusion":"success","html_url":"https://example.test/job/1"}]}'
DELIVER_RUNNING='{"jobs":[{"name":"deliver-sources","status":"in_progress","conclusion":null,"html_url":"https://example.test/job/2"}]}'
NO_DELIVER='{"jobs":[{"name":"helm-package","status":"completed","conclusion":"success","html_url":"https://example.test/job/3"}]}'
DELIVER_FAILED='{"jobs":[{"name":"deliver-sources","status":"completed","conclusion":"failure","html_url":"https://example.test/job/4"}]}'
NO_JOBS='{"jobs":[]}'

# Stands in for "gh api --paginate repos/<repo>/actions/runs/<id>/jobs?per_page=100".
gh() {
  [[ "$3" =~ /actions/runs/([0-9]+)/jobs ]] || { echo "unexpected gh call: $*" >&2; return 1; }
  [[ "${JOBS[${BASH_REMATCH[1]}]:-}" != "FAIL" ]] || { echo "HTTP 502" >&2; return 1; }
  printf '%s\n' "${JOBS[${BASH_REMATCH[1]}]:-$NO_JOBS}"
}

# run <id> <status> <created_at> [event] [head repository]
run() {
  printf '{"id":%s,"status":"%s","created_at":"%s","head_branch":"%s","event":"%s","head_repository":{"full_name":"%s"}}' \
    "$1" "$2" "$3" "$TAG" "${4:-push}" "${5:-$REPO}"
}

# page <run>... : one page of "gh api --paginate .../actions/runs" output
page() {
  local IFS=,
  printf '{"workflow_runs":[%s]}\n' "$*"
}

FAILED=0
# check <name> <expected> <runs listing>: called directly, not in a pipeline, so FAILED survives
check() {
  local name="$1" expected="$2" runs_file="$FIXTURES/$1.json" actual
  printf '%s\n' "$3" > "$runs_file"
  actual="$(tag_deliver_sources_state "$runs_file" "$TAG" "$REPO")"
  if [[ "$actual" == "$expected" ]]; then
    echo "ok   $name"
  else
    echo "FAIL $name"
    echo "     expected: ${expected//$'\t'/<TAB>}"
    echo "     actual:   ${actual//$'\t'/<TAB>}"
    FAILED=$((FAILED + 1))
  fi
}

OK_LINE=$'job\tcompleted\tsuccess\thttps://example.test/job/1'
T0="2026-11-19T10:00:00Z"
T1="2026-11-19T10:00:01Z"

JOBS=([44512542599]="$NO_DELIVER" [44512542888]="$DELIVER_OK")
# the API orders same-second runs arbitrarily; this order made a newest-run-only pick land on helm
check "helm and web started in the same second: web is chosen" "$OK_LINE" \
  "$(page "$(run 44512542888 completed "$T0")" "$(run 44512542599 completed "$T0")")"

JOBS=([20]="$NO_DELIVER" [10]="$DELIVER_OK")
check "newer run without the job does not hide the one that has it" "$OK_LINE" \
  "$(page "$(run 10 completed "$T0")" "$(run 20 completed "$T1")")"

JOBS=([10]="$DELIVER_OK" [20]="$NO_DELIVER")
check "newer run still going without the job does not hide the one that has it" "$OK_LINE" \
  "$(page "$(run 10 completed "$T0")" "$(run 20 in_progress "$T1")")"

JOBS=([10]="$DELIVER_OK")
check "single run is used as before" "$OK_LINE" \
  "$(page "$(run 10 completed "$T0")")"

JOBS=([10]="$DELIVER_RUNNING")
check "job still running is reported with an empty conclusion" $'job\tin_progress\t\thttps://example.test/job/2' \
  "$(page "$(run 10 in_progress "$T0")")"

JOBS=()
check "no run yet is none" "none" \
  "$(page)"

JOBS=([10]="$DELIVER_OK" [99]="$DELIVER_OK")
check "fork run with the same branch name is ignored" "$OK_LINE" \
  "$(page "$(run 10 completed "$T0")" "$(run 99 completed "$T1" push someone/centreon)")"

JOBS=([99]="$DELIVER_OK")
check "fork run alone counts as no run" "none" \
  "$(page "$(run 99 completed "$T0" push someone/centreon)")"

JOBS=([99]="$DELIVER_OK")
check "non-push run with the same branch name is ignored" "none" \
  "$(page "$(run 99 completed "$T0" pull_request)")"

JOBS=([10]="$NO_DELIVER")
check "run still going without the job yet is pending" "pending" \
  "$(page "$(run 10 completed "$T0")" "$(run 20 in_progress "$T1")")"

JOBS=([10]="$NO_DELIVER" [20]="$NO_DELIVER")
check "every run finished without the job is absent" "absent" \
  "$(page "$(run 10 completed "$T0")" "$(run 20 completed "$T1")")"

JOBS=([10]="$DELIVER_OK" [20]="$NO_DELIVER")
check "runs split across paginated pages are compared together" "$OK_LINE" \
  "$(page "$(run 20 completed "$T1")"; page "$(run 10 completed "$T0")")"

JOBS=([10]="$DELIVER_FAILED" [20]="FAIL")
check "jobs call failing on the newest run is pending, not the older run's job" "pending" \
  "$(page "$(run 10 completed "$T0")" "$(run 20 completed "$T1")")"

JOBS=([10]="FAIL")
check "jobs call failing on the only run is pending" "pending" \
  "$(page "$(run 10 completed "$T0")")"

JOBS=()
check "unreadable runs listing counts as no run" "none" '{"workflow_runs":['

if (( FAILED > 0 )); then
  echo "$FAILED test(s) failed"
  exit 1
fi
echo "all tests passed"
