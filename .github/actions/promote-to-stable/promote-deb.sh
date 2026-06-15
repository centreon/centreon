#!/usr/bin/env bash
set -euo pipefail

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

if ! pulp deb repository show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "::error::Nothing to promote, repository $REPOSITORY_NAME does not exist"
  exit 1
fi

VERSION_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.latest_version_href')

# packages of the module are identified by the label set at delivery time,
# the testing pool path scopes the stability and the package distrib name
# scopes the distribution as the apt repository holds all the suites
PACKAGES=$(
  curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" -G \
    --data-urlencode "repository_version=$VERSION_HREF" \
    --data-urlencode "pulp_label_select=module=$MODULE_NAME" \
    --data-urlencode "limit=1000" \
    "$PULP_URL/api/v3/content/deb/packages/" | \
    jq --arg testing_path "$TESTING_POOL_PATH/" --arg distrib_name "$PACKAGE_DISTRIB_NAME" \
      '[.results[] | select((.relative_path | startswith($testing_path)) and (.relative_path | contains($distrib_name)))]'
)
PACKAGES_COUNT=$(echo "$PACKAGES" | jq 'length')

if [[ "$PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, no package of module $MODULE_NAME found in $REPOSITORY_NAME ($TESTING_POOL_PATH/)"
  exit 1
fi

echo "[INFO] $PACKAGES_COUNT packages of module $MODULE_NAME found in $REPOSITORY_NAME ($TESTING_POOL_PATH/)"

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $PACKAGES_COUNT packages would be promoted to $STABLE_POOL_PATH/ ($STABLE_SUITE/main)"
  exit 0
fi

REPOSITORY_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

mkdir -p promoted-packages

for RELATIVE_PATH in $(echo "$PACKAGES" | jq -r '.[].relative_path'); do
  FILE_NAME=$(basename "$RELATIVE_PATH")
  FILE="promoted-packages/$FILE_NAME"
  echo "[INFO] Downloading $PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"
  curl -fsSL -o "$FILE" "$PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"

  echo "[INFO] Promoting $FILE_NAME to $STABLE_POOL_PATH/ ($STABLE_SUITE/main)"
  TASK_HREF=$(
    pulp_upload \
      -F "file=@$FILE" \
      -F "relative_path=$STABLE_POOL_PATH/$FILE_NAME" \
      -F "distribution=$STABLE_SUITE" \
      -F "component=main" \
      -F "repository=$REPOSITORY_HREF" \
      -F "pulp_labels={\"module\": \"$MODULE_NAME\"}" \
      "$PULP_URL/api/v3/content/deb/packages/"
  )
  wait_task "$TASK_HREF"
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $STABLE_SUITE main"
