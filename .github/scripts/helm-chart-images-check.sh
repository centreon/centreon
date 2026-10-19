#!/usr/bin/env bash
# Every ghcr.io image the chart renders must exist. Env: CHART_DIR.
set -euo pipefail

shopt -s nullglob
values_files=("$CHART_DIR"/ci/*-values.yaml)
if [[ ${#values_files[@]} -eq 0 ]]; then
  echo "::error::No CI values file in $CHART_DIR/ci/, cannot render the chart."
  exit 1
fi

rendered=""
for values in "${values_files[@]}"; do
  rendered+="$(helm template ci "$CHART_DIR" -f "$values" --kube-version 1.29.0)"$'\n'
done
images=$(awk '$1 == "image:" || $2 == "image:" { print $NF }' <<< "$rendered" | tr -d "\"'" | awk '/^ghcr\.io\//' | sort -u)
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
