#!/usr/bin/env bash
# Before promoting a chart as stable (MON-212455): every ghcr.io image it
# references by default must already be published, otherwise the stable chart
# would point to missing images. Renders the chart with each CI values file
# (<chart>/ci/*-values.yaml) and resolves every ghcr.io image with crane.
#
# Needs helm and crane on PATH, ghcr.io credentials (crane reads the docker
# config) able to read the image packages.
#
# Expected env vars:
#   CHART_DIR   chart directory (e.g. .github/helm/centreon-poller)
set -euo pipefail

shopt -s nullglob
values_files=("$CHART_DIR"/ci/*-values.yaml)
if [[ ${#values_files[@]} -eq 0 ]]; then
  echo "::error::No CI values file in $CHART_DIR/ci/, cannot render the chart."
  exit 1
fi

images=$(
  for values in "${values_files[@]}"; do
    helm template ci "$CHART_DIR" -f "$values" --kube-version 1.29.0
  done | awk '$1 == "image:" || $2 == "image:" { print $NF }' | tr -d "\"'" | grep '^ghcr\.io/' | sort -u
)
if [[ -z "$images" ]]; then
  echo "No ghcr.io image referenced by $CHART_DIR."
  exit 0
fi

missing=0
while read -r image; do
  if digest=$(crane digest "$image" 2>/dev/null); then
    echo "$image -> $digest"
  else
    echo "::error::$image is not available on ghcr.io (not promoted yet, or not readable by this workflow)."
    missing=1
  fi
done <<< "$images"
exit "$missing"
