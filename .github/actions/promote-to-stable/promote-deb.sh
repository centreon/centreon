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

if ! pulp deb repository show --name "$REPOSITORY_NAME" >/dev/null 2>&1; then
  echo "::error::Nothing to promote, repository $REPOSITORY_NAME does not exist"
  exit 1
fi

RELATIVE_PATHS=$(
  pulp deb repository content list --repository "$REPOSITORY_NAME" --limit 10000 | \
    jq -r --arg testing_path "$TESTING_POOL_PATH/" '.[] | select(.relative_path | startswith($testing_path)) | .relative_path'
)
PACKAGES_COUNT=$(echo "$RELATIVE_PATHS" | grep -c . || true)

if [[ "$PACKAGES_COUNT" -eq 0 ]]; then
  echo "::error::Nothing to promote, no package found in $REPOSITORY_NAME ($TESTING_POOL_PATH/)"
  exit 1
fi

echo "[INFO] $PACKAGES_COUNT packages found in $REPOSITORY_NAME ($TESTING_POOL_PATH/)"

if [[ "$STABILITY" != "stable" ]]; then
  echo "[INFO] Dry run, $PACKAGES_COUNT packages would be promoted to $STABLE_POOL_PATH/ ($STABLE_SUITE/main)"
  exit 0
fi

REPOSITORY_HREF=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.pulp_href')

mkdir -p promoted-packages

for RELATIVE_PATH in $RELATIVE_PATHS; do
  FILE_NAME=$(basename "$RELATIVE_PATH")
  FILE="promoted-packages/$FILE_NAME"
  echo "[INFO] Downloading $PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"
  curl -fsSL -o "$FILE" "$PULP_CONTENT_URL/$BASE_PATH/$RELATIVE_PATH"

  echo "[INFO] Promoting $FILE_NAME to $STABLE_POOL_PATH/ ($STABLE_SUITE/main)"
  # pulp-cli does not allow to set the relative path of deb packages, use the api directly
  # to deliver packages in a pool sub directory per module
  TASK_HREF=$(
    curl -fsSL -u "$PULP_USERNAME:$PULP_PASSWORD" \
      -F "file=@$FILE" \
      -F "relative_path=$STABLE_POOL_PATH/$FILE_NAME" \
      -F "distribution=$STABLE_SUITE" \
      -F "component=main" \
      -F "repository=$REPOSITORY_HREF" \
      "$PULP_URL/pulp/api/v3/content/deb/packages/" | jq -r '.task'
  )
  wait_task "$TASK_HREF"
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $STABLE_SUITE main"
