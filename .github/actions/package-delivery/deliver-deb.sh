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

REPOSITORY_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

for FILE in "${FILES[@]}"; do
  echo "[INFO] Uploading $FILE to $POOL_PATH/ ($SUITE/main, module $MODULE_NAME)"
  # packages are labeled with their module so that promote-to-stable can identify
  # which packages belong to this module, pulp-cli does not allow to set labels nor
  # the relative path of deb packages so the api is used directly
  TASK_HREF=$(
    pulp_upload \
      -F "file=@$FILE" \
      -F "relative_path=$POOL_PATH/$FILE" \
      -F "distribution=$SUITE" \
      -F "component=main" \
      -F "repository=$REPOSITORY_HREF" \
      -F "pulp_labels={\"module\": \"$MODULE_NAME\"}" \
      "$PULP_URL/api/v3/content/deb/packages/"
  )
  wait_task "$TASK_HREF"
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $SUITE main"
