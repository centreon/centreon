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
  local file=$1 name version arch packages
  name=$(dpkg-deb -f "$file" Package)
  version=$(dpkg-deb -f "$file" Version)
  arch=$(dpkg-deb -f "$file" Architecture)

  # the published stable suite is the source of truth for what reached stable.
  # the release_components api is not listable by the OIDC ci-user (403, and no
  # grantable permission exists for it), so check the served Packages index
  # instead: no api access needed and it reflects exactly what stable publishes.
  packages=$(curl -fsSL "$PULP_CONTENT_URL/$BASE_PATH/dists/$STABLE_SUITE/main/binary-$arch/Packages" 2>/dev/null || true)
  # empty: stable suite not published yet (or unreachable) -> treat as not in stable
  [[ -z "$packages" ]] && return 0

  # a package is in stable if a Packages stanza matches both name and version
  if printf '%s\n' "$packages" | awk -v n="$name" -v v="$version" '
       BEGIN { RS = ""; FS = "\n" }
       {
         has_name = 0; has_version = 0
         for (i = 1; i <= NF; i++) {
           if ($i == "Package: " n) has_name = 1
           if ($i == "Version: " v) has_version = 1
         }
         if (has_name && has_version) found = 1
       }
       END { exit found ? 0 : 1 }
     '; then
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
