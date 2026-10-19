#!/usr/bin/env bash
# Package the pre-release (Harbor images) and, on testing branches, the release candidate.
# appVersion comes from .version (MAJOR). Env: CHART_DIR, STABILITY, REF_NAME, RUN_NUMBER, HARBOR_REGISTRY, OUT_DIR.
set -euo pipefail

output="${GITHUB_OUTPUT:-/dev/stdout}"
chart=$(basename "$CHART_DIR")
version=$(awk '$1 == "version:" { gsub(/"/, "", $2); print $2; exit }' "$CHART_DIR/Chart.yaml")
MAJOR=$(. ./.version && echo "$MAJOR")

case "$STABILITY" in
  unstable)
    image_tag="$MAJOR"
    prerelease="$version-$(tr -c 'a-z0-9\n' '-' <<< "$REF_NAME").$RUN_NUMBER"
    ;;
  testing)
    image_tag="$REF_NAME"
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
  helm package "$CHART_DIR" --version "$version" --app-version "$MAJOR" --destination "$OUT_DIR"
  echo "candidate=$version" >> "$output"
fi
