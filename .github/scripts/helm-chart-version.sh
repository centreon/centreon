#!/usr/bin/env bash
# Print the chart version: major = year of .version MAJOR, minor.patch from Chart.yaml.
# Env: CHART_DIR.
set -euo pipefail

version=$(awk '$1 == "version:" { gsub(/"/, "", $2); print $2; exit }' "$CHART_DIR/Chart.yaml")
MAJOR=$(. ./.version && echo "$MAJOR")
echo "${MAJOR%%.*}.${version#*.}"
