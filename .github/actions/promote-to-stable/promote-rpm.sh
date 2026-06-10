#!/usr/bin/env bash
set -euo pipefail

if ! pulp rpm repository show --name "$TRACKING_REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "::error::Nothing to promote, testing repository $TRACKING_REPOSITORY_NAME does not exist"
  exit 1
fi

PACKAGES=$(pulp rpm repository content list --repository "$TRACKING_REPOSITORY_NAME" --limit 1000)
PACKAGES_COUNT=$(echo "$PACKAGES" | jq 'length')

if [[ "$PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, testing repository $TRACKING_REPOSITORY_NAME is empty"
  exit 1
fi

echo "[INFO] $PACKAGES_COUNT packages found in $TRACKING_REPOSITORY_NAME"

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $PACKAGES_COUNT packages would be promoted to $STABLE_REPOSITORY_PREFIX repositories"
  exit 0
fi

for ARCH in noarch x86_64; do
  CONTENT=$(echo "$PACKAGES" | jq --arg arch "$ARCH" 'map(select(.arch == $arch) | {pulp_href})')
  ARCH_PACKAGES_COUNT=$(echo "$CONTENT" | jq 'length')

  if [[ "$ARCH_PACKAGES_COUNT" -eq 0 ]]; then
    echo "[INFO] No $ARCH package to promote"
    continue
  fi

  STABLE_REPOSITORY_NAME="$STABLE_REPOSITORY_PREFIX-$ARCH"
  STABLE_BASE_PATH="$STABLE_BASE_PATH_PREFIX/$ARCH"

  if ! pulp rpm repository show --name "$STABLE_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm repository $STABLE_REPOSITORY_NAME"
    pulp rpm repository create --name "$STABLE_REPOSITORY_NAME" >/dev/null
  fi

  if ! pulp rpm distribution show --name "$STABLE_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm distribution $STABLE_REPOSITORY_NAME served at $STABLE_BASE_PATH"
    pulp rpm distribution create --name "$STABLE_REPOSITORY_NAME" --base-path "$STABLE_BASE_PATH" --repository "$STABLE_REPOSITORY_NAME" >/dev/null
  fi

  echo "[INFO] Promoting $ARCH_PACKAGES_COUNT packages to $STABLE_REPOSITORY_NAME"
  pulp rpm repository content modify --repository "$STABLE_REPOSITORY_NAME" --add-content "$CONTENT" >/dev/null

  echo "[INFO] Publishing repository $STABLE_REPOSITORY_NAME"
  pulp rpm publication create --repository "$STABLE_REPOSITORY_NAME" >/dev/null

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$STABLE_BASE_PATH/"
done
