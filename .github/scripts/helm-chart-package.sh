#!/usr/bin/env bash
# Package the pre-release (Harbor images) and, on testing branches, the release candidate.
# Env: CHART_DIR, STABILITY, REF_NAME, MAJOR_VERSION, RUN_NUMBER, HARBOR_REGISTRY, OUT_DIR.
set -euo pipefail

output="${GITHUB_OUTPUT:-/dev/stdout}"
chart=$(basename "$CHART_DIR")
version=$(awk '$1 == "version:" { gsub(/"/, "", $2); print $2; exit }' "$CHART_DIR/Chart.yaml")

# Poller images only exist for on-prem majors (same mapping as install-poller).
if [[ "$MAJOR_VERSION" == *.10 ]]; then
  image_major="$MAJOR_VERSION"
else
  year=$((10#${MAJOR_VERSION%%.*}))
  month=$((10#${MAJOR_VERSION##*.}))
  (( month < 10 )) && year=$((year - 1))
  (( year < 26 )) && year=26
  image_major=$(printf '%02d.10' "$year")
fi

case "$STABILITY" in
  unstable)
    if [[ "$REF_NAME" == develop ]]; then
      image_tag=develop
    else
      image_tag="${REF_NAME#dev-}"
      image_tag="${image_tag%.x}"
    fi
    prerelease="$version-$(tr -c 'a-z0-9\n' '-' <<< "$REF_NAME").$RUN_NUMBER"
    ;;
  testing)
    image_tag="${REF_NAME%%-*}-$image_major-next"
    prerelease="$version-rc.$RUN_NUMBER"
    ;;
  *)
    echo "::error::No pre-release for stability '$STABILITY'."
    exit 1
    ;;
esac

src="$(mktemp -d)/$chart"
cp -r "$CHART_DIR" "$src"
sed -i -E "s#(repository: *)ghcr\.io/centreon/(centreon-[a-z0-9-]+)#\1$HARBOR_REGISTRY/\2-trixie#" "$src/values.yaml"
grep -E '^\s*repository:' "$src/values.yaml"
helm package "$src" --version "$prerelease" --app-version "$image_tag" --destination "$OUT_DIR"
echo "prerelease=$prerelease" >> "$output"

if [[ "$STABILITY" == testing ]]; then
  helm package "$CHART_DIR" --version "$version" --destination "$OUT_DIR"
  echo "candidate=$version" >> "$output"
fi
