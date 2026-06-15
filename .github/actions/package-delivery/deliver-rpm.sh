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

# upload a package through the pulp api, with retry on transient failures
# (concurrent deliveries can race on artifact creation), echoes the task href
pulp_upload() {
  local attempt response http_code body
  for attempt in 1 2 3; do
    response=$(curl -sS -u "$PULP_USERNAME:$PULP_PASSWORD" -w $'\n%{http_code}' "$@" 2>/dev/null) || response=""
    http_code=${response##*$'\n'}
    body=${response%$'\n'*}
    if [[ "$http_code" == "202" ]]; then
      echo "$body" | jq -r '.task'
      return 0
    fi
    echo "[WARN] upload attempt $attempt/3 failed (HTTP ${http_code:-network-error}), retrying..." >&2
    sleep $((attempt * 3))
  done
  echo "::error::Upload failed after 3 attempts (HTTP ${http_code:-network-error})" >&2
  return 1
}

FILES=(*.rpm)
if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "::error::No rpm package found to deliver"
  exit 1
fi

mkdir -p noarch x86_64
for FILE in "${FILES[@]}"; do
  ARCH=$(echo "$FILE" | grep -oP '(x86_64|noarch)' | head -1 || true)
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

  # Upload the packages without associating them to the repository, then add them
  # all in a single repository modification: this creates one repository version
  # for the whole batch instead of one per package. Packages are labeled with
  # their module so that promote-to-stable can identify them; pulp-cli does not
  # allow to set labels on upload so the api is used directly.
  CONTENT="[]"
  for FILE in "${ARCH_FILES[@]}"; do
    echo "[INFO] Uploading $(basename "$FILE") (module $MODULE_NAME)"
    TASK_HREF=$(
      pulp_upload \
        -F "file=@$FILE" \
        -F "pulp_labels={\"module\": \"$MODULE_NAME\"}" \
        "$PULP_URL/api/v3/content/rpm/packages/"
    )
    wait_task "$TASK_HREF"
    PACKAGE_HREF=$(
      curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" "$PULP_URL$TASK_HREF" | \
        jq -r '.created_resources[] | select(contains("/content/rpm/packages/"))'
    )
    CONTENT=$(echo "$CONTENT" | jq --arg href "$PACKAGE_HREF" '. + [$href]')
  done

  REPOSITORY_HREF=$(pulp rpm repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

  echo "[INFO] Adding ${#ARCH_FILES[@]} packages to repository $REPOSITORY_NAME"
  MODIFY_TASK_HREF=$(
    curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" \
      -X POST -H "Content-Type: application/json" \
      -d "{\"add_content_units\": $CONTENT}" \
      "$PULP_URL${REPOSITORY_HREF}modify/" | jq -r '.task'
  )
  wait_task "$MODIFY_TASK_HREF"

  echo "[INFO] Publishing repository $REPOSITORY_NAME"
  pulp rpm publication create --repository "$REPOSITORY_NAME" >/dev/null

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$BASE_PATH/"
done
