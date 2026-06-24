#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob

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

# upload a package through the pulp api, with retry on transient failures
# (concurrent deliveries can race on artifact creation), echoes the task href
pulp_upload() {
  local attempt response http_code body
  for attempt in 1 2 3; do
    response=$(curl -sS -H "Authorization: Github $PULP_TOKEN" -w $'\n%{http_code}' "$@" 2>/dev/null) || response=""
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

# refuse delivering a package that is already published in the stable suite.
# rebuilding a testing/unstable version with a different content is fine, but a
# version that already reached stable must never be re-delivered: pulp
# deduplicates packages by (package, version, architecture) repository wide, so
# the new content would silently evict the stable one. bump the version instead.
assert_not_in_stable() {
  local file=$1 name version arch repository_version stable_component package_href count
  name=$(dpkg-deb -f "$file" Package)
  version=$(dpkg-deb -f "$file" Version)
  arch=$(dpkg-deb -f "$file" Architecture)
  repository_version=$(pulp deb repository show --name "$REPOSITORY_NAME" | jq -r '.latest_version_href')

  # the "main" release component of the stable suite, empty if nothing is stable yet
  stable_component=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" -G \
      --data-urlencode "repository_version=$repository_version" \
      --data-urlencode "distribution=$STABLE_SUITE" \
      --data-urlencode "component=main" \
      --data-urlencode "fields=pulp_href" \
      "$PULP_URL/api/v3/content/deb/release_components/" | jq -r '.results[0].pulp_href // empty'
  )
  [[ -z "$stable_component" ]] && return 0

  # the package unit already present for this name/version/architecture, if any
  # (pulp keeps a single unit per name+version+architecture repository wide)
  package_href=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" -G \
      --data-urlencode "repository_version=$repository_version" \
      --data-urlencode "package=$name" \
      --data-urlencode "version=$version" \
      --data-urlencode "architecture=$arch" \
      --data-urlencode "fields=pulp_href" \
      "$PULP_URL/api/v3/content/deb/packages/" | jq -r '.results[0].pulp_href // empty'
  )
  [[ -z "$package_href" ]] && return 0

  # is that unit associated with the stable suite? (the release_component filter
  # on the packages endpoint is broken server side, so go through the join)
  count=$(
    curl -fsSL -H "Authorization: Github $PULP_TOKEN" -G \
      --data-urlencode "repository_version=$repository_version" \
      --data-urlencode "release_component=$stable_component" \
      --data-urlencode "package=$package_href" \
      --data-urlencode "limit=1" \
      "$PULP_URL/api/v3/content/deb/package_release_components/" | jq -r '.count'
  )
  if [[ "$count" -gt 0 ]]; then
    echo "::error::$name $version ($arch) is already published in the stable suite $STABLE_SUITE; refusing to deliver it to $SUITE. Bump the package version for a new build."
    return 1
  fi
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

PULP_LABELS=$(jq -cn \
  --arg mod        "$MODULE_NAME" \
  --arg git_commit "${GITHUB_SHA:-}" \
  --arg git_ref    "${GITHUB_REF:-}" \
  --arg run_id     "${GITHUB_RUN_ID:-}" \
  --arg actor      "${GITHUB_ACTOR:-}" \
  --arg workflow   "${GITHUB_WORKFLOW:-}" \
  '{"module": $mod, "git_commit": $git_commit, "git_ref": $git_ref, "github_run_id": $run_id, "github_actor": $actor, "github_workflow": $workflow}')

for FILE in "${FILES[@]}"; do
  assert_not_in_stable "$FILE"
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
      -F "pulp_labels=$PULP_LABELS" \
      "$PULP_URL/api/v3/content/deb/packages/"
  )
  wait_task "$TASK_HREF"
done

echo "[INFO] Publishing repository $REPOSITORY_NAME"
pulp deb publication create --repository "$REPOSITORY_NAME" --structured >/dev/null

echo "::notice::Packages are available with: deb $PULP_CONTENT_URL/$BASE_PATH/ $SUITE main"
