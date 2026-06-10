#!/usr/bin/env bash
set -euo pipefail

if ! pulp deb repository show --name "$TRACKING_REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "::error::Nothing to promote, testing repository $TRACKING_REPOSITORY_NAME does not exist"
  exit 1
fi

RELATIVE_PATHS=$(pulp deb repository content list --repository "$TRACKING_REPOSITORY_NAME" --limit 1000 | jq -r '.[].relative_path')
PACKAGES_COUNT=$(echo "$RELATIVE_PATHS" | grep -c . || true)

if [[ "$PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, testing repository $TRACKING_REPOSITORY_NAME is empty"
  exit 1
fi

echo "[INFO] $PACKAGES_COUNT packages found in $TRACKING_REPOSITORY_NAME"

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $PACKAGES_COUNT packages would be promoted to $REPOSITORY_NAME ($STABLE_SUITE/main)"
  exit 0
fi

if ! pulp deb repository show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "[INFO] Creating deb repository $REPOSITORY_NAME"
  pulp deb repository create --name "$REPOSITORY_NAME" >/dev/null
fi

if ! pulp deb distribution show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "[INFO] Creating deb distribution $REPOSITORY_NAME served at $BASE_PATH"
  pulp deb distribution create --name "$REPOSITORY_NAME" --base-path "$BASE_PATH" --repository "$REPOSITORY_NAME" >/dev/null
fi

mkdir -p promoted-packages

for RELATIVE_PATH in $RELATIVE_PATHS; do
  FILE="promoted-packages/$(basename "$RELATIVE_PATH")"
  echo "[INFO] Downloading $PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"
  curl -fsSL -o "$FILE" "$PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"

  echo "[INFO] Promoting $FILE to $REPOSITORY_NAME ($STABLE_SUITE/main)"
  pulp deb content upload --file "$FILE" --repository "$REPOSITORY_NAME" --distribution "$STABLE_SUITE" --component main >/dev/null
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $STABLE_SUITE main"
