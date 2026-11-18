#!/usr/bin/env bash
# Chart.yaml version must be X.Y.Z; on PRs (BASE_REF set), a changed chart (outside ci/) needs a higher minor.patch.
# The major is not compared: the CI sets it from .version at packaging.
# Env: CHART_DIR, BASE_REF.
set -euo pipefail

minor_patch() {
  awk '$1 == "version:" { gsub(/"/, "", $2); sub(/^[^.]*\./, "", $2); print $2; exit }'
}

bash .github/scripts/helm-chart-version.sh > /dev/null

if [[ -z "${BASE_REF:-}" ]]; then
  exit 0
fi

head_version=$(minor_patch < "$CHART_DIR/Chart.yaml")
if ! base_chart=$(git show "origin/$BASE_REF:$CHART_DIR/Chart.yaml" 2>/dev/null); then
  echo "New chart $CHART_DIR (x.$head_version): nothing to compare."
  exit 0
fi
base_version=$(minor_patch <<< "$base_chart")

if git diff --quiet "origin/$BASE_REF...HEAD" -- "$CHART_DIR" ":(exclude)$CHART_DIR/ci"; then
  echo "$CHART_DIR unchanged against $BASE_REF (x.$base_version)."
  exit 0
fi

highest=$(printf '%s\n%s\n' "$base_version" "$head_version" | sort -V | tail -n1)
if [[ "$head_version" == "$base_version" || "$highest" != "$head_version" ]]; then
  echo "::error file=$CHART_DIR/Chart.yaml::$CHART_DIR changed but its minor.patch ($head_version) is not higher than on $BASE_REF ($base_version): bump it."
  exit 1
fi
echo "$CHART_DIR: x.$base_version -> x.$head_version"
