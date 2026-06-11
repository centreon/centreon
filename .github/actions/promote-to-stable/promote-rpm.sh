#!/usr/bin/env bash
set -euo pipefail

declare -A ARCH_CONTENT
TOTAL_PACKAGES_COUNT=0

for ARCH in noarch x86_64; do
  TESTING_REPOSITORY_NAME="$TESTING_REPOSITORY_PREFIX-$ARCH"

  if ! pulp rpm repository show --name "$TESTING_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Testing repository $TESTING_REPOSITORY_NAME does not exist"
    continue
  fi

  CONTENT=$(
    pulp rpm repository content list --repository "$TESTING_REPOSITORY_NAME" --limit 1000 | \
      jq --arg module_path "$MODULE_NAME/" 'map(select(.location_href | startswith($module_path)) | {pulp_href})'
  )
  ARCH_PACKAGES_COUNT=$(echo "$CONTENT" | jq 'length')

  echo "[INFO] $ARCH_PACKAGES_COUNT $ARCH packages of module $MODULE_NAME found in $TESTING_REPOSITORY_NAME"
  ARCH_CONTENT[$ARCH]="$CONTENT"
  TOTAL_PACKAGES_COUNT=$((TOTAL_PACKAGES_COUNT + ARCH_PACKAGES_COUNT))
done

if [[ "$TOTAL_PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, no package of module $MODULE_NAME found in $TESTING_REPOSITORY_PREFIX repositories"
  exit 1
fi

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $TOTAL_PACKAGES_COUNT packages would be promoted to $STABLE_REPOSITORY_PREFIX repositories"
  exit 0
fi

for ARCH in noarch x86_64; do
  CONTENT="${ARCH_CONTENT[$ARCH]:-[]}"
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
