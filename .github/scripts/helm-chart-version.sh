#!/usr/bin/env bash
# Print the chart version: major = year of .version MAJOR, minor.patch from Chart.yaml.
# Env: CHART_DIR.
set -euo pipefail

version=$(awk '$1 == "version:" { gsub(/"/, "", $2); print $2; exit }' "$CHART_DIR/Chart.yaml")
if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "::error file=$CHART_DIR/Chart.yaml::$CHART_DIR version '$version' must be X.Y.Z (the CI adds pre-release suffixes)." >&2
  exit 1
fi
MAJOR=$(. ./.version && echo "$MAJOR")
echo "${MAJOR%%.*}.${version#*.}"
