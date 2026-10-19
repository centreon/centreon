#!/usr/bin/env bash
# Fail when a chart changed against the PR base without a higher `version` in
# its Chart.yaml (MON-212455): published chart versions are immutable, a chart
# change must ship under a new one. Files under <chart>/ci/ (CI values, not
# packaged) don't count as a change.
#
# Expected env vars:
#   BASE_REF    PR base branch, fetched as origin/$BASE_REF
#   CHART_DIR   chart directory (e.g. .github/helm/centreon-poller)
set -euo pipefail

chart_version() {
  awk '$1 == "version:" { gsub(/"/, "", $2); print $2; exit }'
}

head_version=$(chart_version < "$CHART_DIR/Chart.yaml")

if ! base_chart=$(git show "origin/$BASE_REF:$CHART_DIR/Chart.yaml" 2>/dev/null); then
  echo "New chart $CHART_DIR ($head_version): nothing to compare."
  exit 0
fi
base_version=$(chart_version <<< "$base_chart")

if git diff --quiet "origin/$BASE_REF...HEAD" -- "$CHART_DIR" ":(exclude)$CHART_DIR/ci"; then
  echo "$CHART_DIR unchanged against $BASE_REF ($base_version)."
  exit 0
fi

highest=$(printf '%s\n%s\n' "$base_version" "$head_version" | sort -V | tail -n1)
if [[ "$head_version" == "$base_version" || "$highest" != "$head_version" ]]; then
  echo "::error file=$CHART_DIR/Chart.yaml::$CHART_DIR changed but its version ($head_version) is not higher than on $BASE_REF ($base_version): bump it."
  exit 1
fi
echo "$CHART_DIR: $base_version -> $head_version"
