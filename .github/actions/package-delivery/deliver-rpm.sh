#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob

FILES=(*.rpm)
if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "::error::No rpm package found to deliver"
  exit 1
fi

mkdir -p noarch x86_64
for FILE in "${FILES[@]}"; do
  ARCH=$(echo "$FILE" | grep -oP '(x86_64|noarch)' || true)
  if [[ -z "$ARCH" ]]; then
    echo "::error::Cannot find the architecture of package $FILE"
    exit 1
  fi
  mv "$FILE" "$ARCH"
done

TRACKING_CONTENT="[]"

for ARCH in noarch x86_64; do
  ARCH_FILES=("$ARCH"/*.rpm)
  if [[ ${#ARCH_FILES[@]} -eq 0 ]]; then
    continue
  fi

  REPOSITORY_NAME="$REPOSITORY_PREFIX-$ARCH"
  BASE_PATH="$BASE_PATH_PREFIX/$ARCH"

  if ! pulp rpm repository show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm repository $REPOSITORY_NAME"
    pulp rpm repository create --name "$REPOSITORY_NAME" --retain-package-versions 1 >/dev/null
  fi

  if ! pulp rpm distribution show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm distribution $REPOSITORY_NAME served at $BASE_PATH"
    pulp rpm distribution create --name "$REPOSITORY_NAME" --base-path "$BASE_PATH" --repository "$REPOSITORY_NAME" >/dev/null
  fi

  CONTENT="[]"
  for FILE in "${ARCH_FILES[@]}"; do
    echo "[INFO] Uploading $FILE"
    PACKAGE_HREF=$(pulp rpm content upload --file "$FILE" | jq -r '.pulp_href')
    CONTENT=$(echo "$CONTENT" | jq --arg href "$PACKAGE_HREF" '. + [{"pulp_href": $href}]')
    TRACKING_CONTENT=$(echo "$TRACKING_CONTENT" | jq --arg href "$PACKAGE_HREF" '. + [{"pulp_href": $href}]')
  done

  echo "[INFO] Adding ${#ARCH_FILES[@]} packages to repository $REPOSITORY_NAME"
  pulp rpm repository content modify --repository "$REPOSITORY_NAME" --add-content "$CONTENT" >/dev/null

  echo "[INFO] Publishing repository $REPOSITORY_NAME"
  pulp rpm publication create --repository "$REPOSITORY_NAME" >/dev/null

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$BASE_PATH/"
done

if [[ -n "$TRACKING_REPOSITORY_NAME" ]]; then
  if ! pulp rpm repository show --name "$TRACKING_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm tracking repository $TRACKING_REPOSITORY_NAME"
    pulp rpm repository create --name "$TRACKING_REPOSITORY_NAME" --retain-package-versions 1 >/dev/null
  fi
  echo "[INFO] Adding packages to tracking repository $TRACKING_REPOSITORY_NAME"
  pulp rpm repository content modify --repository "$TRACKING_REPOSITORY_NAME" --add-content "$TRACKING_CONTENT" >/dev/null
fi
