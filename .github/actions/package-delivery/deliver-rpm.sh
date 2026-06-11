#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob

# wait for a pulp api task to complete
wait_task() {
  local task_href=$1
  local state
  while :; do
    state=$(curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" "$PULP_URL$task_href" | jq -r '.state')
    case "$state" in
      completed)
        return 0
        ;;
      failed|canceled)
        echo "::error::Task $task_href $state: $(curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" "$PULP_URL$task_href" | jq -c '.error')"
        return 1
        ;;
      *)
        sleep 3
        ;;
    esac
  done
}

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

  REPOSITORY_HREF=$(pulp rpm repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

  for FILE in "${ARCH_FILES[@]}"; do
    echo "[INFO] Uploading $(basename "$FILE") to $REPOSITORY_NAME (module $MODULE_NAME)"
    # packages are labeled with their module so that promote-to-stable can identify
    # which packages belong to this module, pulp-cli does not allow to set labels
    # on upload so the api is used directly
    TASK_HREF=$(
      curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" \
        -F "file=@$FILE" \
        -F "repository=$REPOSITORY_HREF" \
        -F "pulp_labels={\"module\": \"$MODULE_NAME\"}" \
        "$PULP_URL/pulp/api/v3/content/rpm/packages/" | jq -r '.task'
    )
    wait_task "$TASK_HREF"
  done

  echo "[INFO] Publishing repository $REPOSITORY_NAME"
  pulp rpm publication create --repository "$REPOSITORY_NAME" >/dev/null

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$BASE_PATH/"
done
