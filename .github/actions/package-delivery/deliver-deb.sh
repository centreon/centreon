#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob

FILES=(*.deb)
if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "::error::No deb package found to deliver"
  exit 1
fi

if ! pulp deb repository show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "[INFO] Creating deb repository $REPOSITORY_NAME"
  pulp deb repository create --name "$REPOSITORY_NAME" >/dev/null
fi

if ! pulp deb distribution show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "[INFO] Creating deb distribution $REPOSITORY_NAME served at $BASE_PATH"
  pulp deb distribution create --name "$REPOSITORY_NAME" --base-path "$BASE_PATH" --repository "$REPOSITORY_NAME" >/dev/null
fi

if [[ -n "$TRACKING_REPOSITORY_NAME" ]] && ! pulp deb repository show --name "$TRACKING_REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "[INFO] Creating deb tracking repository $TRACKING_REPOSITORY_NAME"
  pulp deb repository create --name "$TRACKING_REPOSITORY_NAME" >/dev/null
fi

for FILE in "${FILES[@]}"; do
  echo "[INFO] Uploading $FILE to $REPOSITORY_NAME ($SUITE/main)"
  pulp deb content upload --file "$FILE" --repository "$REPOSITORY_NAME" --distribution "$SUITE" --component main >/dev/null

  if [[ -n "$TRACKING_REPOSITORY_NAME" ]]; then
    pulp deb content upload --file "$FILE" --repository "$TRACKING_REPOSITORY_NAME" >/dev/null
  fi
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $SUITE main"
