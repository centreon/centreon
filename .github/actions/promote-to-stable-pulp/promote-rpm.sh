#!/usr/bin/env bash
set -euo pipefail

# wait for a pulp api task to complete
wait_task() {
  local task_href=$1
  local state
  while :; do
    state=$(curl -fsSL -H "Authorization: Github $PULP_TOKEN" "$PULP_URL$task_href" | jq -r '.state')
    case "$state" in
      completed)
        return 0
        ;;
      failed|canceled)
        echo "::error::Task $task_href $state: $(curl -fsSL -H "Authorization: Github $PULP_TOKEN" "$PULP_URL$task_href" | jq -c '.error')"
        return 1
        ;;
      *)
        sleep 3
        ;;
    esac
  done
}

declare -A ARCH_CONTENT
TOTAL_PACKAGES_COUNT=0

for ARCH in noarch x86_64; do
  TESTING_REPOSITORY_NAME="$TESTING_REPOSITORY_PREFIX-$ARCH"

  if ! pulp rpm repository show --name "$TESTING_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Testing repository $TESTING_REPOSITORY_NAME does not exist"
    continue
  fi

  VERSION_HREF=$(pulp rpm repository show --name "$TESTING_REPOSITORY_NAME" | jq -r '.latest_version_href')

  # packages of the module are identified by the label set at delivery time
  CONTENT=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" -G \
      --data-urlencode "repository_version=$VERSION_HREF" \
      --data-urlencode "pulp_label_select=module=$MODULE_NAME" \
      --data-urlencode "limit=1000" \
      "$PULP_URL/api/v3/content/rpm/packages/" | jq '[.results[].pulp_href]'
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
    # no --retain-package-versions on stable: it keeps the full version history
    # (unlike testing/unstable which retain only the latest), matching
    # create-rpm-repos.sh. normally create-repos pre-creates it, this is a fallback.
    pulp rpm repository create --name "$STABLE_REPOSITORY_NAME" >/dev/null
  fi

  if ! pulp rpm distribution show --name "$STABLE_REPOSITORY_NAME" >/dev/null 2>&1; then
    echo "[INFO] Creating rpm distribution $STABLE_REPOSITORY_NAME served at $STABLE_BASE_PATH"
    pulp rpm distribution create --name "$STABLE_REPOSITORY_NAME" --base-path "$STABLE_BASE_PATH" --repository "$STABLE_REPOSITORY_NAME" >/dev/null
  fi

  STABLE_REPOSITORY_HREF=$(pulp rpm repository show --name "$STABLE_REPOSITORY_NAME" | jq -r '.pulp_href')

  echo "[INFO] Promoting $ARCH_PACKAGES_COUNT packages to $STABLE_REPOSITORY_NAME"
  # pulp-cli repository content modify does not resolve content by pulp_href, use the api directly
  TASK_HREF=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" \
      -X POST -H "Content-Type: application/json" \
      -d "{\"add_content_units\": $CONTENT}" \
      "$PULP_URL${STABLE_REPOSITORY_HREF}modify/" | jq -r '.task'
  )
  wait_task "$TASK_HREF"

  echo "[INFO] Publishing repository $STABLE_REPOSITORY_NAME"
  pulp rpm publication create --repository "$STABLE_REPOSITORY_NAME" >/dev/null

  echo "::notice::Packages are available at $PULP_CONTENT_URL/$STABLE_BASE_PATH/"
done
