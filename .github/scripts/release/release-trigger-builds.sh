#!/usr/bin/env bash
# release-trigger-builds logic, shared by centreon, centreon-collect and centreon-modules.
# usage: release-trigger-builds.sh prepare|dispatch (inputs from the environment, see each function)
set -euo pipefail

MAPPING_PATH=.github/scripts/release/release-components.json

out() { echo "$1=$2" >> "$GITHUB_OUTPUT"; }

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

  # previous stable bundle tag: same major and scope; a release (new major) may fall back to the scope's latest
  local previous
  previous=$(git tag -l --sort=-version:refname "release-$scope-*-$major" | head -n 1)
  if [[ -z "$previous" ]]; then
    if [[ "$type" == "hotfix" ]]; then
      echo "::error::A hotfix needs a previous stable bundle tag release-$scope-*-$major, none found"
      exit 1
    fi
    previous=$(git tag -l --sort=-version:refname "release-$scope-*" | head -n 1)
    [[ -n "$previous" ]] || { echo "::error::No stable bundle tag at all for scope $scope"; exit 1; }
    echo "::warning::No stable bundle tag for $major, diffing against $previous"
  fi
  local previous_sha
  previous_sha=$(git rev-parse "refs/tags/$previous^{commit}")
  echo "Previous stable bundle tag: $previous ($previous_sha)"

  local head_sha exists=false
  if head_sha=$(git rev-parse -q --verify "refs/remotes/origin/$branch^{commit}"); then
    exists=true
    echo "Branch $branch exists at $head_sha"
  else
    # cloud release from develop, onprem release from dev-XX.YY.x, hotfix from the previous stable bundle tag
    local base
    if [[ "$type" == "hotfix" ]]; then
      base=$previous
      head_sha=$previous_sha
    else
      base=develop
      [[ "$scope" == "onprem" ]] && base="dev-$major.x"
      head_sha=$(git rev-parse -q --verify "refs/remotes/origin/$base^{commit}") \
        || { echo "::error::Base branch $base not found"; exit 1; }
    fi
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

  # the mapping is read at the target, never from develop: each line carries its own component paths
  local components_file="$RUNNER_TEMP/release-components.json"
  if ! git show "$head_sha:$MAPPING_PATH" > "$components_file" 2> /dev/null; then
    echo "::error::$branch has no $MAPPING_PATH: add it to the branch (cherry-pick from its dev branch) and re-run"
    exit 1
  fi
  jq -e '.schema_version == 1 and (.components | type == "object")' "$components_file" > /dev/null \
    || { echo "::error::$MAPPING_PATH on $branch is not a valid mapping"; exit 1; }

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
  mapfile -t workflows < <(jq -r '.[] | select(.action != "skipped") | .workflow' "$manifest")
  if (( ${#workflows[@]} == 0 )); then
    echo "::warning::No component to dispatch ($DISPATCH_CONTENT)"
    return 0
  fi

  # check every target at the branch before dispatching anything
  local wf missing=()
  for wf in "${workflows[@]}"; do
    if ! git show "$HEAD_SHA:.github/workflows/$wf" 2> /dev/null | grep -q -E '^[[:space:]]+workflow_dispatch:'; then
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
