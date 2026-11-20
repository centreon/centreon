#!/usr/bin/env bash
# release-trigger-builds logic, shared by centreon, centreon-collect and centreon-modules.
# usage: release-trigger-builds.sh prepare|dispatch (inputs from the environment, see each function)
set -euo pipefail

MAPPING_PATH=.github/scripts/release/release-components.json

out() { echo "$1=$2" >> "$GITHUB_OUTPUT"; }

# true when the root "on:" of a workflow file declares workflow_dispatch, as a scalar,
# a flow or block sequence, or a mapping key (other mentions, e.g. in expressions, don't count)
declares_dispatch() {
  awk '
    function is_dispatch(v) { sub(/[[:space:]]+#.*$/, "", v); return v ~ /^workflow_dispatch[[:space:]]*(:.*)?$/ }
    /^("on"|'"'"'on'"'"'|on):/ {
      in_on = 1; ind = 0; v = $0; sub(/^[^:]*:[[:space:]]*/, "", v); sub(/[[:space:]]+#.*$/, "", v)
      if (v ~ /^[[{]/) { gsub(/[][{}[:space:]]/, "", v); n = split(v, items, ","); for (i = 1; i <= n; i++) if (is_dispatch(items[i])) found = 1 }
      else if (is_dispatch(v)) found = 1
      next
    }
    !in_on || /^[[:space:]]*(#|$)/ { next }
    /^-[[:space:]]/ { v = $0; sub(/^-[[:space:]]*/, "", v); if (is_dispatch(v)) found = 1; next }
    /^[^[:space:]]/ { in_on = 0; next }
    {
      match($0, /^[[:space:]]+/); if (!ind) ind = RLENGTH
      if (RLENGTH == ind) { v = substr($0, ind + 1); sub(/^-[[:space:]]*/, "", v); if (is_dispatch(v)) found = 1 }
    }
    END { exit !found }
  ' "$1"
}

# in: DISPATCH_TARGET_RELEASE_BRANCH, DRY_RUN, GITHUB_REPOSITORY, BRANCH_TOKEN (PAT, branch creation only)
# out: branch, branch_exists, head_sha, previous_stable_bundle_tag(_sha), release_scope, release_type, major, components_file
prepare() {
  local branch=$DISPATCH_TARGET_RELEASE_BRANCH
  if [[ ! "$branch" =~ ^(release|hotfix)-([0-9]{2}\.[0-9]{2})-next$ ]]; then
    echo "::error::'$branch' is not a release-XX.YY-next or hotfix-XX.YY-next branch"
    exit 1
  fi
  local type=${BASH_REMATCH[1]} major=${BASH_REMATCH[2]} scope=cloud
  # XX.10 majors are onprem, every other major is cloud (same rule as get-environment)
  [[ "$major" =~ \.10$ ]] && scope=onprem
  echo "Branch $branch: type $type, major $major, scope $scope"

  # previous stable bundle tag: same major and scope; a release (new major) falls back to
  # the latest tag of the highest lower major (same ordering as the release-new action)
  local previous tag candidates=""
  previous=$(git tag -l --sort=-version:refname "release-$scope-*-$major" | head -n 1)
  if [[ -z "$previous" ]]; then
    if [[ "$type" == "hotfix" ]]; then
      echo "::error::A hotfix needs a previous stable bundle tag release-$scope-*-$major, none found"
      exit 1
    fi
    while read -r tag; do
      [[ "$tag" =~ ^release-$scope-[0-9]{8}-([0-9]{2}\.[0-9]{2})$ ]] || continue
      [[ "$(printf '%s\n%s\n' "${BASH_REMATCH[1]}" "$major" | sort -V | head -n 1)" == "${BASH_REMATCH[1]}" ]] || continue
      candidates+="${BASH_REMATCH[1]} $tag"$'\n'
    done < <(git tag -l "release-$scope-*")
    previous=$(printf '%s' "$candidates" | sort -k1,1V -k2,2V | tail -n 1 | cut -d ' ' -f2)
    [[ -n "$previous" ]] || { echo "::error::No stable bundle tag for a major lower than $major (scope $scope)"; exit 1; }
    echo "::warning::No stable bundle tag for $major, diffing against $previous"
  fi
  local previous_sha
  previous_sha=$(git rev-parse "refs/tags/$previous^{commit}")
  echo "Previous stable bundle tag: $previous ($previous_sha)"

  local head_sha base exists=false
  if head_sha=$(git rev-parse -q --verify "refs/remotes/origin/$branch^{commit}"); then
    exists=true
    echo "Branch $branch exists at $head_sha"
  else
    # cloud release from develop, onprem release from dev-XX.YY.x, hotfix from the previous stable bundle tag
    if [[ "$type" == "hotfix" ]]; then
      base=$previous
      head_sha=$previous_sha
    else
      base=develop
      [[ "$scope" == "onprem" ]] && base="dev-$major.x"
      head_sha=$(git rev-parse -q --verify "refs/remotes/origin/$base^{commit}") \
        || { echo "::error::Base branch $base not found"; exit 1; }
    fi
  fi

  # the mapping is read at the target, never from develop: each line carries its own component paths;
  # checked before the branch is created, so a target without a valid mapping leaves nothing behind
  local components_file="$RUNNER_TEMP/release-components.json"
  if ! git show "$head_sha:$MAPPING_PATH" > "$components_file" 2> /dev/null; then
    if [[ "$exists" == "true" ]]; then
      echo "::error::$branch has no $MAPPING_PATH: add it to the branch (cherry-pick from dev-$major.x or develop) and re-run"
    else
      echo "::error::$base ($head_sha) has no $MAPPING_PATH: create $branch from it by hand, add the file (cherry-pick from dev-$major.x or develop), then re-run"
    fi
    exit 1
  fi
  jq -e '
    .schema_version == 1
    and (.components | type == "object" and length > 0 and all(.[];
          (.workflow | type == "string") and (.bundle_component | type == "string" or . == null)
          and (.paths | type == "array" and length > 0 and all(.[]; type == "string" and length > 0))))
    and ((.full_extras // []) | type == "array" and all(.[]; (.workflow | type == "string")))
  ' "$components_file" > /dev/null || { echo "::error::$MAPPING_PATH at $head_sha is not a valid mapping"; exit 1; }

  if [[ "$exists" == "false" ]]; then
    if [[ "$DRY_RUN" == "true" ]]; then
      echo "[dry-run] would create $branch from $base ($head_sha)"
    else
      echo "Creating $branch from $base ($head_sha)"
      local response
      response=$(GH_TOKEN=$BRANCH_TOKEN gh api -X POST "repos/$GITHUB_REPOSITORY/git/refs" \
        -f ref="refs/heads/$branch" -f sha="$head_sha" --jq .object.sha) \
        || { echo "::error::Cannot create branch $branch"; exit 1; }
      [[ "$response" == "$head_sha" ]] || { echo "::error::Branch $branch created at $response, expected $head_sha"; exit 1; }
    fi
  fi

  out branch "$branch"
  out branch_exists "$exists"
  out head_sha "$head_sha"
  out previous_stable_bundle_tag "$previous"
  out previous_stable_bundle_tag_sha "$previous_sha"
  out release_scope "$scope"
  out release_type "$type"
  out major "$major"
  out components_file "$components_file"
}

# in: DISPATCH_CONTENT (REGULAR|CHANGED_ONLY|FULL), BRANCH, HEAD_SHA, COMPONENTS_FILE, CHANGED_FILE, DRY_RUN, GH_TOKEN
# out: manifest (path of the JSON manifest: every component and what was done with it)
# shellcheck disable=SC2153 # inputs come from the environment
dispatch() {
  case "$DISPATCH_CONTENT" in
    REGULAR|CHANGED_ONLY|FULL) ;;
    *) echo "::error::Unknown dispatch_content '$DISPATCH_CONTENT'"; exit 1 ;;
  esac
  local changed='[]'
  if [[ -s "$CHANGED_FILE" ]]; then
    changed=$(jq -R -s -c 'split("\n") | map(select(length > 0))' "$CHANGED_FILE")
  fi
  local unknown
  unknown=$(jq -r --argjson changed "$changed" '$changed - (.components | keys) | join(" ")' "$COMPONENTS_FILE")
  [[ -z "$unknown" ]] || { echo "::error::Changed components missing from the mapping: $unknown"; exit 1; }

  local manifest="$RUNNER_TEMP/release-trigger-builds-manifest.json"
  jq --arg mode "$DISPATCH_CONTENT" --argjson changed "$changed" --arg dry "$DRY_RUN" '
    ([.components | to_entries[] | {key, workflow: .value.workflow, bundle_component: .value.bundle_component,
        selected: (if $mode == "CHANGED_ONLY" then (.key | IN($changed[])) else true end)}]
     + [.full_extras[]? | {key: (.workflow | sub("\\.ya?ml$"; "")), workflow, bundle_component,
        selected: ($mode == "FULL")}])
    | map(. + {action: (if .selected | not then "skipped"
                         elif $dry == "true" then "dry-run" else "dispatch" end)} | del(.selected))
  ' "$COMPONENTS_FILE" > "$manifest"
  jq . "$manifest"
  out manifest "$manifest"

  {
    echo "### Dispatch ($DISPATCH_CONTENT) on $BRANCH"
    echo ""
    echo "| component | workflow | action |"
    echo "|---|---|---|"
    jq -r '.[] | "| \(.key) | \(.workflow) | \(.action) |"' "$manifest"
  } >> "${GITHUB_STEP_SUMMARY:-/dev/null}"

  local workflows
  local selected
  selected=$(jq -r '.[] | select(.action != "skipped") | .workflow' "$manifest")
  mapfile -t workflows <<< "$selected"
  [[ -n "$selected" ]] || workflows=()
  if (( ${#workflows[@]} == 0 )); then
    echo "::warning::No component to dispatch ($DISPATCH_CONTENT)"
    return 0
  fi

  # check every target at the branch before dispatching anything
  local wf missing=()
  for wf in "${workflows[@]}"; do
    if ! git show "$HEAD_SHA:.github/workflows/$wf" > "$RUNNER_TEMP/target-workflow.yml" 2> /dev/null \
      || ! declares_dispatch "$RUNNER_TEMP/target-workflow.yml"; then
      missing+=("$wf")
    fi
  done
  if (( ${#missing[@]} > 0 )); then
    echo "::error::Not dispatchable on $BRANCH (missing or without workflow_dispatch): ${missing[*]}"
    exit 1
  fi

  if [[ "$DRY_RUN" == "true" ]]; then
    for wf in "${workflows[@]}"; do echo "[dry-run] gh workflow run $wf --ref $BRANCH"; done
    return 0
  fi

  # dispatch everything, then fail if any dispatch failed
  local failed=()
  for wf in "${workflows[@]}"; do
    echo "Dispatching $wf on $BRANCH"
    gh workflow run "$wf" --ref "$BRANCH" || failed+=("$wf")
  done
  if (( ${#failed[@]} > 0 )); then
    echo "::error::Dispatch failed for: ${failed[*]}"
    exit 1
  fi
  echo "Runs: ${GITHUB_SERVER_URL:-https://github.com}/$GITHUB_REPOSITORY/actions?query=branch%3A$BRANCH"
}

case "${1:-}" in
  prepare) prepare ;;
  dispatch) dispatch ;;
  *) echo "usage: $0 prepare|dispatch" >&2; exit 2 ;;
esac
