#!/usr/bin/env bash
# release-trigger-builds logic, shared by centreon, centreon-collect and centreon-modules.
# usage: release-trigger-builds.sh prepare|validate|tag|mapping|record|dispatch
#   legacy mode (testing_tag_builds false): prepare, then dispatch on the branch
#   testing-tag mode: job 1 prepare, validate, tag; job 2 mapping, record, dispatch on the tag
# Design: delivery-tooling docs/release/testing-bundles.md ("release-trigger-builds in detail")
set -euo pipefail

MAPPING_PATH=.github/scripts/release/release-components.json
CHAIN=(centreon-collect centreon centreon-modules)
LABELS_VALUE=system,database
YQ=${YQ:-yq}
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

out() { echo "$1=$2" >> "$GITHUB_OUTPUT"; }
summary() { cat >> "${GITHUB_STEP_SUMMARY:-/dev/null}"; }
fail() { echo "::error::$*" >&2; exit 1; }

# a check that needs data we could not read: a dry run warns and goes on, a real run fails
soft_fail() {
  if [[ "$DRY_RUN" == "true" ]]; then echo "::warning::[dry-run] $*"; else fail "$*"; fi
}

# events of the root "on:" of a workflow file, read through yq (YAML 1.1 parsers turn "on" into true)
workflow_on() {
  "$YQ" -o=json '.' "$1" | jq -c '(.on // .["true"]) as $o
    | if ($o | type) == "string" then {events: [$o], inputs: {}}
      elif ($o | type) == "array" then {events: $o, inputs: {}}
      elif ($o | type) == "object" then {events: ($o | keys),
             inputs: (($o.workflow_dispatch // {}) | if type == "object" then (.inputs // {}) else {} end)}
      else {events: [], inputs: {}} end'
}

# in: DISPATCH_TARGET_RELEASE_BRANCH, DRY_RUN, GITHUB_REPOSITORY, BRANCH_TOKEN (PAT, branch creation only)
# out: mode (legacy|testing), branch, branch_exists, head_sha, previous_stable_bundle_tag(_sha),
#      release_scope, release_type, major, components_file
# In testing-tag mode the branch is never created here: job 1 creates it after every check passed.
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
  check_mapping "$components_file" || { echo "::error::$MAPPING_PATH at $head_sha is not a valid mapping"; exit 1; }

  local mode=legacy
  [[ "$(jq -r '.testing_tag_builds // false' "$components_file")" == "true" ]] && mode=testing
  echo "Mode: $mode (testing_tag_builds at the target)"
  if [[ "$mode" == "legacy" && -n "${RELEASE_NUMBER:-}" ]]; then
    echo "::warning::release_number $RELEASE_NUMBER is ignored: $branch is not in testing-tag mode"
  fi

  if [[ "$exists" == "false" && "$mode" == "legacy" ]]; then
    create_branch "$branch" "$base" "$head_sha"
  fi

  out mode "$mode"
  out branch "$branch"
  out branch_exists "$exists"
  out head_sha "$head_sha"
  out previous_stable_bundle_tag "$previous"
  out previous_stable_bundle_tag_sha "$previous_sha"
  out release_scope "$scope"
  out release_type "$type"
  out major "$major"
  out components_file "$components_file"
  [[ -n "${base:-}" ]] && out branch_base "$base"
  return 0
}

check_mapping() {
  jq -e '
    .schema_version == 1
    and (.components | type == "object" and length > 0 and all(.[];
          (.workflow | type == "string") and (.bundle_component | type == "string" or . == null)
          and (.paths | type == "array" and length > 0 and all(.[]; type == "string" and length > 0))
          and ((.scopes // ["cloud", "onprem"]) | type == "array" and length > 0
               and all(.[]; . == "cloud" or . == "onprem"))))
    and ((.full_extras // []) | type == "array" and all(.[];
          (.workflow | type == "string") and (.bundle_component | type == "string" or . == null)))
    and ((.testing_tag_builds // false) | type == "boolean")
    and ((.pre_tag_workflows // []) | type == "array" and all(.[];
          (type == "string" and length > 0)
          or (type == "object" and (.workflow | type == "string" and length > 0)
              and ((.allowed_failed_steps // []) | type == "array" and all(.[]; type == "string" and length > 0)))))
  ' "$1" > /dev/null
}

create_branch() {
  local branch=$1 base=$2 sha=$3 response
  if [[ "$DRY_RUN" == "true" ]]; then
    echo "[dry-run] would create $branch from $base ($sha)"
    return 0
  fi
  echo "Creating $branch from $base ($sha)"
  response=$(GH_TOKEN=$BRANCH_TOKEN gh api -X POST "repos/$GITHUB_REPOSITORY/git/refs" \
    -f ref="refs/heads/$branch" -f sha="$sha" --jq .object.sha) \
    || { echo "::error::Cannot create branch $branch"; exit 1; }
  [[ "$response" == "$sha" ]] || { echo "::error::Branch $branch created at $response, expected $sha"; exit 1; }
}

# --- remote state of the 3 repositories (tags, -next branches), read once per repository ---

remote_url() {
  # shellcheck disable=SC2059 # the template is ours: one "%s" placeholder, the repository
  printf "${REMOTE_URL_TEMPLATE:-https://github.com/${GITHUB_REPOSITORY%%/*}/%s.git}" "$1"
}

# git with the PAT in a header passed through the environment: never in the URL nor on the command line
# (needs a checkout with persist-credentials: false, or GitHub refuses a duplicate Authorization header)
remote_git() {
  local auth
  auth=$(printf 'x-access-token:%s' "$REMOTE_TOKEN" | base64 | tr -d '\n')
  [[ -n "${GITHUB_ACTIONS:-}" ]] && echo "::add-mask::$auth" >&2
  GIT_TERMINAL_PROMPT=0 GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=http.extraHeader \
    GIT_CONFIG_VALUE_0="Authorization: Basic $auth" git "$@"
}

# primes the cache of one repository; returns 1 when its refs can't be read (never cached then)
fetch_remote_refs() {
  local cache="$RUNNER_TEMP/refs-$1.txt" listing
  [[ -f "$cache" ]] && return 0
  listing=$(remote_git ls-remote --refs "$(remote_url "$1")" 'refs/tags/testing-*' 'refs/tags/release-*' 'refs/heads/*-next') \
    || return 1
  awk '{print $2}' <<< "$listing" > "$cache.tmp" && mv "$cache.tmp" "$cache"
}

# reads only primed caches: validate() primes the 3 repositories first and fails if one is unreadable
remote_refs() {
  local cache="$RUNNER_TEMP/refs-$1.txt"
  [[ -f "$cache" ]] || { echo "::error::refs of $1 not loaded" >&2; return 1; }
  cat "$cache"
}

remote_tags() { remote_refs "$1" | sed -n 's|^refs/tags/||p'; }
# here-strings, not "| grep -q": grep exiting early would make pipefail report a SIGPIPE as "not found"
remote_has_branch() { grep -Fqx "refs/heads/$2" <<< "$(remote_refs "$1")"; }

# iterations N already tagged in a repository for one release: testing-<scope>-<type>-<number>-<major>-N
bundle_iterations() {
  local repo=$1 prefix=$2
  remote_tags "$repo" | sed -n "s|^${prefix//./\\.}-\([1-9][0-9]*\)$|\1|p" | sort -n
}

# --- S3 bundle status (read in job 1, written in job 2) ---

status_root() { if [[ "${STATUS_DRY_RUN:-false}" == "true" ]]; then echo dry-run/bundles; else echo bundles; fi; }

# reads the global file of a bundle into $1; returns 0 read, 3 missing, 1 unreadable
read_global() {
  local out=$1 key=$2
  [[ "${S3_AVAILABLE:-false}" == "true" ]] || return 1
  # shellcheck source=../../actions/release-bundle-status/bundle-s3.sh
  source "$SCRIPT_DIR/../../actions/release-bundle-status/bundle-s3.sh"
  bundle_s3_get_global "$key" "$out"
}

# failed run of a pre-tag workflow: accepted only when every failed step is one of the allowed ones
# usage: pre_tag_failure_allowed <run id> <allowed step names, JSON array>; prints the failed steps,
# returns 0 accepted, 1 refused, 2 jobs unreadable
pre_tag_failure_allowed() {
  local jobs
  jobs=$(gh run view "$1" -R "$GITHUB_REPOSITORY" --json jobs) || return 2
  jq -r '[.jobs[]? | (.steps // [])[] | select(.conclusion == "failure") | .name] | unique | join(", ")' <<< "$jobs" \
    || return 2
  jq -e --argjson allowed "$2" '
    [.jobs[]? | (.steps // [])[] | select(.conclusion == "failure") | .name] as $failed
    | ($failed | length) > 0 and ($failed - $allowed | length) == 0
      and all(.jobs[]? | select(.conclusion | IN("success", "skipped", "neutral") | not);
              any((.steps // [])[]; .conclusion == "failure"))' <<< "$jobs" > /dev/null || return 1
}

# in (job 1, testing-tag mode): RELEASE_NUMBER, BRANCH, BRANCH_EXISTS, HEAD_SHA, RELEASE_SCOPE, RELEASE_TYPE,
#   MAJOR, COMPONENTS_FILE, PREVIOUS_STABLE_BUNDLE_TAG_SHA, DISPATCH_CONTENT, DRY_RUN, GITHUB_REPOSITORY,
#   REMOTE_TOKEN (PAT, reads the 3 repositories), GH_TOKEN (runs of this repository), BUCKET, S3_AVAILABLE,
#   ABANDON_PREVIOUS_BUNDLE (opener only: open N+1 although bundle N has components without state)
# out: tag, iteration, role, bundle_path, upstream, stop_after_branch,
#      abandoned_bundle, abandoned_components, abandoned_repos (only when a bundle is abandoned)
# shellcheck disable=SC2153 # inputs come from the environment
validate() {
  local scope=$RELEASE_SCOPE type=$RELEASE_TYPE major=$MAJOR number=$RELEASE_NUMBER
  local repo=${GITHUB_REPOSITORY#*/} tag
  local -a errors=()

  # release number: YYYYMMNN, release = NN 00, hotfix = NN 01..99
  if [[ ! "$number" =~ ^20[0-9]{2}(0[1-9]|1[0-2])([0-9]{2})$ ]]; then
    fail "release_number '$number' is not a YYYYMMNN release number"
  fi
  local nn=${BASH_REMATCH[2]}
  [[ "$type" == "release" && "$nn" != "00" ]] && fail "A release number ends in 00 (got $number for $BRANCH)"
  [[ "$type" == "hotfix" && "$nn" == "00" ]] && fail "A hotfix number never ends in 00 (got $number for $BRANCH)"

  # .version at the target, parsed like get-environment (sourced, here without the environment):
  # another major there and every component run dies in get-environment, the bundle can never be closed
  local version_major=""
  if git show "$HEAD_SHA:.version" > "$RUNNER_TEMP/target-version" 2> /dev/null; then
    # shellcheck disable=SC2016 # expanded by the inner shell
    version_major=$(env -i bash -c '. "$1" > /dev/null 2>&1; printf "%s" "${MAJOR:-}"' _ "$RUNNER_TEMP/target-version") \
      || version_major=""
  fi
  if [[ "$version_major" != "$major" ]]; then
    errors+=(".version at $HEAD_SHA has MAJOR=${version_major:-(not readable)} but $BRANCH is a $major branch: the component builds would all be refused. $([[ "$BRANCH_EXISTS" == "true" ]] && echo "Set MAJOR=$major in .version on the branch (or dispatch the branch of $version_major)" || echo "$BRANCH doesn't exist yet and the .version of its base doesn't say $major: create it by hand with MAJOR=$major in .version"), then dispatch again")
  fi

  local self_index=-1 i
  for i in "${!CHAIN[@]}"; do [[ "${CHAIN[$i]}" == "$repo" ]] && self_index=$i; done
  (( self_index >= 0 )) || fail "$repo is not part of the bundle chain ${CHAIN[*]}"

  # fail closed: a repository whose refs can't be read would look like a repository without tags
  local r
  for r in "${CHAIN[@]}"; do
    fetch_remote_refs "$r" || fail "Cannot read the tags and branches of $r (token access, GitHub outage?)"
  done
  local all_tags
  all_tags=$(for r in "${CHAIN[@]}"; do remote_tags "$r"; done | sort -u)

  # release not cut yet
  grep -Fqx "release-$scope-$number-$major" <<< "$all_tags" \
    && fail "Release $number is already cut: stable bundle tag release-$scope-$number-$major exists"
  local latest_stable
  latest_stable=$(sed -n "s|^release-$scope-\([0-9]\{8\}\)-${major//./\\.}$|\1|p" <<< "$all_tags" | sort -n | tail -n 1)
  if [[ -n "$latest_stable" ]] && (( number <= latest_stable )); then
    fail "Release number $number is not above $latest_stable, the latest release of $major"
  fi

  # line order: never below the highest release number of the line (get-environment would refuse the runs)
  local line_numbers highest
  line_numbers=$(sed -n "s|^testing-$scope-$type-\([0-9]\{8\}\)-${major//./\\.}-[1-9][0-9]*$|\1|p" <<< "$all_tags" | sort -u)
  highest=$(tail -n 1 <<< "$line_numbers")
  if [[ -n "$highest" ]]; then
    (( number < highest )) && fail "Release number $number is lower than $highest, the latest of the line $scope $type $major"
    if (( number > highest )) && ! grep -Fqx "release-$scope-$highest-$major" <<< "$all_tags"; then
      echo "::warning::Release $highest of the line $scope $type $major was never cut, its bundles are superseded by $number"
    fi
  fi

  # cloud: a release and a hotfix never share a major; an open train = testing tag without stable tag.
  # Only the latest number of the other line counts: older uncut ones are superseded, they can't build
  if [[ "$scope" == "cloud" ]]; then
    local other=hotfix other_latest
    [[ "$type" == "hotfix" ]] && other=release
    other_latest=$(sed -n "s|^testing-cloud-$other-\([0-9]\{8\}\)-${major//./\\.}-[1-9][0-9]*$|\1|p" <<< "$all_tags" | sort -u | tail -n 1)
    if [[ -n "$other_latest" ]] && ! grep -Fqx "release-cloud-$other_latest-$major" <<< "$all_tags"; then
      fail "Cloud $other train $other_latest still open on $major: a cloud release and hotfix never share a major"
    fi
  fi

  # role: the first repository of the chain having the branch opens the bundle, the next ones join it
  local -a upstream=()
  for (( i = 0; i < self_index; i++ )); do
    remote_has_branch "${CHAIN[$i]}" "$BRANCH" && upstream+=("${CHAIN[$i]}")
  done
  # repositories taking part in the bundle: this one, and every repository of the chain having the branch
  local -a bundle_repos=()
  for r in "${CHAIN[@]}"; do
    if [[ "$r" == "$repo" ]] || remote_has_branch "$r" "$BRANCH"; then bundle_repos+=("$r"); fi
  done
  local prefix="testing-$scope-$type-$number-$major" latest=0 n
  for r in "${CHAIN[@]}"; do
    n=$(bundle_iterations "$r" "$prefix" | tail -n 1)
    [[ -n "$n" ]] && (( n > latest )) && latest=$n
  done
  local role iteration root global abandon=${ABANDON_PREVIOUS_BUNDLE:-false}
  local abandoned_bundle="" abandoned_components="" abandoned_repos="" finding
  root=$(status_root)
  global="$RUNNER_TEMP/global-build-status.json"
  if (( ${#upstream[@]} == 0 )); then
    role=opener
    iteration=$(( latest + 1 ))
    if (( latest > 0 )); then
      # the previous bundle must be finished: every expected component has a state
      local rc=0
      read_global "$global" "$root/$scope/$major/$type/$number/$latest/global-build-status.json" || rc=$?
      if (( rc == 0 )); then
        # a repository tagged in it without a record yet is still starting: its plan is not known
        local pending unrecorded=""
        for r in "${CHAIN[@]}"; do
          grep -Fqx "$latest" <<< "$(bundle_iterations "$r" "$prefix")" || continue
          [[ "$(jq --arg r "$r" '(.repos // {}) | has($r)' "$global")" == "true" ]] || unrecorded+=" $r"
        done
        pending=$(jq -r '([.repos[]?.expected[]?] | unique) - (.components | keys) | join(" ")' "$global")
        if [[ -n "$unrecorded" ]]; then
          finding="Bundle $prefix-$latest is tagged but not recorded yet in:$unrecorded."
          if [[ "$abandon" == "true" ]]; then
            echo "::warning::$finding Abandoned (abandon_previous_bundle): $prefix-$iteration opens without it"
            abandoned_repos=${unrecorded# }
          else
            errors+=("$finding Wait for their release-trigger-builds, or re-run its failed jobs; if that run can't be re-run any more, ask the delivery team to delete the tag $prefix-$latest there. Last resort, once re-running failed: dispatch again with abandon_previous_bundle ticked")
          fi
        fi
        if [[ -n "$pending" ]]; then
          finding="Bundle $prefix-$latest is still building, no state yet for: $pending."
          if [[ "$abandon" == "true" ]]; then
            echo "::warning::$finding Abandoned (abandon_previous_bundle): $prefix-$iteration opens without them"
            abandoned_components=$pending
          else
            errors+=("$finding Wait, or cancel their runs; if they are stuck, re-run them first. Last resort, once re-running failed: dispatch again with abandon_previous_bundle ticked")
          fi
        fi
        if [[ -n "$abandoned_repos$abandoned_components" ]]; then
          abandoned_bundle="$prefix-$latest"
          # a run of N already past get-environment would still deliver over N+1: none may be in progress
          local busy="" runs_n
          for r in "${CHAIN[@]}"; do
            grep -Fqx "$latest" <<< "$(bundle_iterations "$r" "$prefix")" || continue
            if ! runs_n=$(GH_TOKEN=$REMOTE_TOKEN gh run list -R "${GITHUB_REPOSITORY%%/*}/$r" --branch "$abandoned_bundle" \
                            --limit 100 --json status,workflowName); then
              soft_fail "Cannot list the runs of $abandoned_bundle in $r"
              continue
            fi
            busy+=$(jq -r --arg r "$r" '[.[] | select(.status != "completed") | " \($r)/\(.workflowName)"] | unique | join("")' \
                      <<< "$runs_n") || fail "Unreadable run list of $abandoned_bundle in $r"
          done
          [[ -z "$busy" ]] || errors+=("Bundle $abandoned_bundle still has runs in progress:$busy. Cancel them first, then dispatch again with abandon_previous_bundle ticked")
        elif [[ "$abandon" == "true" ]]; then
          echo "::warning::abandon_previous_bundle is ticked but bundle $prefix-$latest is finished: nothing to abandon"
        fi
      elif (( rc == 3 )); then
        echo "::warning::Bundle $prefix-$latest has no status file: nothing to wait for"
        [[ "$abandon" != "true" ]] || echo "::warning::abandon_previous_bundle is ticked but bundle $prefix-$latest has no status file: nothing to abandon"
      else
        soft_fail "Cannot read the status of bundle $prefix-$latest (bucket unreachable?)"
      fi
    fi
  else
    role=joiner
    iteration=$latest
    [[ "$abandon" != "true" ]] || echo "::warning::abandon_previous_bundle is ignored: $repo joins a bundle, only the opener (${upstream[0]}) abandons one"
    (( latest > 0 )) || fail "No bundle of $number yet: dispatch ${upstream[0]} first, it opens the bundle"
    for r in "${upstream[@]}"; do
      grep -Fqx "$latest" <<< "$(bundle_iterations "$r" "$prefix")" \
        || errors+=("$r has $BRANCH but is not tagged in $prefix-$latest: dispatch $r first")
    done
    if grep -Fqx "$latest" <<< "$(bundle_iterations "$repo" "$prefix")"; then
      errors+=("$prefix-$latest is already started here: to rebuild, start bundle $((latest + 1)) from ${upstream[0]}")
    fi
    # chain guard: every component expected by the earlier repositories delivered successfully
    if (( ${#errors[@]} == 0 )); then
      local rc=0
      read_global "$global" "$root/$scope/$major/$type/$number/$latest/global-build-status.json" || rc=$?
      if (( rc == 0 )); then
        for r in "${upstream[@]}"; do
          if [[ "$(jq --arg r "$r" '.repos | has($r)' "$global")" != "true" ]]; then
            errors+=("$r has no record in bundle $prefix-$latest: re-run the failed jobs of its release-trigger-builds")
            continue
          fi
          local not_ok
          not_ok=$(jq -r --arg r "$r" '.repos[$r].expected[] as $c
                     | select(.components[$c].state != "success")
                     | "\($c)=\(.components[$c].state // "no state yet")"' "$global" | tr '\n' ' ')
          [[ -z "$not_ok" ]] || errors+=("Chain guard: $r is not all success in $prefix-$latest: $not_ok")
        done
      elif (( rc == 3 )); then
        soft_fail "Bundle $prefix-$latest has no status file yet: the earlier repositories did not record it"
      else
        soft_fail "Cannot read the status of bundle $prefix-$latest (bucket unreachable?)"
      fi
    fi
  fi
  tag="$prefix-$iteration"

  # workflows committing to the branch must be done before it is tagged
  local stop_after_branch=false wf runs conclusion allowed failed_steps rc_steps
  local -a pre_tag=()
  mapfile -t pre_tag < <(jq -r '.pre_tag_workflows // [] | .[] | if type == "object" then .workflow else . end' "$COMPONENTS_FILE")
  if (( ${#pre_tag[@]} > 0 )); then
    if [[ "$BRANCH_EXISTS" != "true" ]]; then
      stop_after_branch=true
    else
      for wf in "${pre_tag[@]}"; do
        runs=$(gh run list -R "$GITHUB_REPOSITORY" --workflow "$wf" --branch "$BRANCH" --limit 1 \
                 --json status,conclusion,url,headSha,databaseId) || { soft_fail "Cannot list the $wf runs on $BRANCH"; continue; }
        if [[ "$(jq length <<< "$runs")" == 0 ]]; then
          soft_fail "No $wf run on $BRANCH yet: wait for it to start (or start it), then dispatch again once it is done"
          continue
        elif [[ "$(jq -r '.[0].status' <<< "$runs")" != "completed" ]]; then
          errors+=("$wf is running on $BRANCH ($(jq -r '.[0].url' <<< "$runs")): wait for it, then re-run")
          continue
        fi
        conclusion=$(jq -r '.[0].conclusion' <<< "$runs")
        if ! [[ "$conclusion" =~ ^(success|skipped|neutral)$ ]]; then
          # a workflow may end in failure on purpose, after its work is done: only its allowed steps may fail
          allowed=$(jq -c --arg wf "$wf" '[.pre_tag_workflows[] | objects | select(.workflow == $wf)
                                           | (.allowed_failed_steps // [])[]]' "$COMPONENTS_FILE")
          failed_steps="" rc_steps=1
          if [[ "$conclusion" == "failure" && "$allowed" != "[]" ]]; then
            rc_steps=0
            failed_steps=$(pre_tag_failure_allowed "$(jq -r '.[0].databaseId' <<< "$runs")" "$allowed") || rc_steps=$?
          fi
          if (( rc_steps == 2 )); then
            soft_fail "Cannot read the jobs of the last $wf run on $BRANCH ($(jq -r '.[0].url' <<< "$runs"))"
            continue
          elif (( rc_steps != 0 )); then
            errors+=("The last $wf run on $BRANCH did not succeed ($(jq -r '.[0].url' <<< "$runs"))${failed_steps:+, failed steps: $failed_steps}")
            continue
          fi
          echo "$wf ended in failure on $BRANCH only in its allowed steps ($failed_steps): accepted"
        fi
        if [[ "$(jq -r '.[0].headSha' <<< "$runs")" != "$HEAD_SHA" ]]; then
          errors+=("The last $wf run on $BRANCH ran on $(jq -r '.[0].headSha' <<< "$runs"), not on its head $HEAD_SHA: start $wf on the branch, then dispatch again")
        fi
      done
      # a pre-tag workflow may have pushed since the checkout: the tag must carry its commit
      local remote_head
      remote_head=$(remote_git ls-remote "$(remote_url "$repo")" "refs/heads/$BRANCH" | cut -f1) || remote_head=""
      if [[ -z "$remote_head" ]]; then
        soft_fail "Cannot read the head of $BRANCH in $repo"
      elif [[ "$remote_head" != "$HEAD_SHA" ]]; then
        errors+=("$BRANCH moved to $remote_head since this run started (pre-tag workflow?): dispatch again")
      fi
    fi
  fi

  # every workflow to dispatch exists at the branch head and accepts workflow_dispatch
  local plan
  plan=$(build_manifest testing "$scope" "$DISPATCH_CONTENT" "${CHANGED_FILE:-}")
  while read -r wf; do
    [[ -n "$wf" ]] || continue
    if ! git show "$HEAD_SHA:.github/workflows/$wf" > "$RUNNER_TEMP/target-workflow.yml" 2> /dev/null; then
      errors+=("$wf is missing at $HEAD_SHA")
    elif ! workflow_on "$RUNNER_TEMP/target-workflow.yml" > "$RUNNER_TEMP/target-on.json"; then
      errors+=("$wf at $HEAD_SHA can't be parsed as YAML")
    elif ! jq -e 'any(.events[]; . == "workflow_dispatch")' "$RUNNER_TEMP/target-on.json" > /dev/null; then
      errors+=("$wf at $HEAD_SHA does not accept workflow_dispatch")
    fi
  done < <(jq -r '.[] | select(.action == "dispatch" or .action == "dry-run") | .workflow' <<< "$plan")

  if (( ${#errors[@]} > 0 )); then
    for i in "${errors[@]}"; do echo "::error::$i"; done
    exit 1
  fi

  local upstream_list=${upstream[*]:-}
  echo "Bundle $tag: $repo is the $role${upstream_list:+ (after $upstream_list)}"
  {
    echo "### Testing bundle $tag"
    echo ""
    echo "| | |"
    echo "|---|---|"
    echo "| repository | $repo ($role${upstream_list:+, after $upstream_list}) |"
    echo "| branch | $BRANCH at \`$HEAD_SHA\` |"
    echo "| release number | $number |"
    echo "| dry run | $DRY_RUN |"
    [[ "$stop_after_branch" == "true" ]] && echo "| note | the branch is created first; re-run once ${pre_tag[*]} is done |"
    [[ -n "$abandoned_bundle" ]] && echo "| abandoned | $abandoned_bundle (no state: ${abandoned_components:-none}; not recorded: ${abandoned_repos:-none}) |"
    echo ""
    echo "| component | workflow | action |"
    echo "|---|---|---|"
    jq -r '.[] | "| \(.key) | \(.workflow) | \(.action)\(if .expected then " (expected)" else "" end) |"' <<< "$plan"
  } | summary

  out tag "$tag"
  out iteration "$iteration"
  out role "$role"
  out bundle_repos "${bundle_repos[*]}"
  out bundle_path "bundles/$scope/$major/$type/$number/$iteration"
  out upstream "$upstream_list"
  out stop_after_branch "$stop_after_branch"
  if [[ -n "$abandoned_bundle" ]]; then
    out abandoned_bundle "$abandoned_bundle"
    out abandoned_components "$abandoned_components"
    out abandoned_repos "$abandoned_repos"
  fi
}

# in: BRANCH, BRANCH_EXISTS, BRANCH_BASE, HEAD_SHA, TAG, STOP_AFTER_BRANCH, DRY_RUN, GITHUB_REPOSITORY, BRANCH_TOKEN
# out: tagged (true|false), stopped (true|false)
# shellcheck disable=SC2153 # inputs come from the environment
tag() {
  if [[ "$BRANCH_EXISTS" != "true" ]]; then
    create_branch "$BRANCH" "$BRANCH_BASE" "$HEAD_SHA"
    if [[ "$STOP_AFTER_BRANCH" == "true" ]]; then
      echo "::notice::$BRANCH created: re-run release-trigger-builds once the pre-tag workflows are done on it"
      out tagged false
      out stopped true
      return 0
    fi
  fi
  if [[ "$DRY_RUN" == "true" ]]; then
    echo "[dry-run] would create the annotated tag $TAG at $HEAD_SHA"
    out tagged false
    out stopped false
    return 0
  fi
  local object ref
  object=$(GH_TOKEN=$BRANCH_TOKEN gh api -X POST "repos/$GITHUB_REPOSITORY/git/tags" \
    -f tag="$TAG" -f message="$TAG" -f object="$HEAD_SHA" -f type=commit --jq .sha) \
    || fail "Cannot create the tag object $TAG"
  ref=$(GH_TOKEN=$BRANCH_TOKEN gh api -X POST "repos/$GITHUB_REPOSITORY/git/refs" \
    -f ref="refs/tags/$TAG" -f sha="$object" --jq .ref) \
    || fail "Cannot create refs/tags/$TAG (does it already exist?)"
  [[ "$ref" == "refs/tags/$TAG" ]] || fail "Unexpected ref $ref for $TAG"
  echo "Tagged $TAG at $HEAD_SHA"
  out tagged true
  out stopped false
}

# in: HEAD_SHA; out: components_file (the mapping read at the tagged commit, for job 2)
mapping() {
  local components_file="$RUNNER_TEMP/release-components.json"
  git show "$HEAD_SHA:$MAPPING_PATH" > "$components_file" || fail "No $MAPPING_PATH at $HEAD_SHA"
  check_mapping "$components_file" || fail "$MAPPING_PATH at $HEAD_SHA is not a valid mapping"
  out components_file "$components_file"
}

# every component and extra of the mapping, with what is done with it
# usage: build_manifest <legacy|testing> <scope> <REGULAR|CHANGED_ONLY|FULL> <changed components file>
build_manifest() {
  local mode=$1 scope=$2 content=$3 changed_file=$4 changed='[]' unknown
  case "$content" in
    REGULAR|CHANGED_ONLY|FULL) ;;
    *) fail "Unknown dispatch_content '$content'" ;;
  esac
  if [[ -n "$changed_file" && -s "$changed_file" ]]; then
    changed=$(jq -R -s -c 'split("\n") | map(select(length > 0))' "$changed_file")
  fi
  unknown=$(jq -r --argjson changed "$changed" '$changed - (.components | keys) | join(" ")' "$COMPONENTS_FILE")
  [[ -z "$unknown" ]] || fail "Changed components missing from the mapping: $unknown"
  jq -c --arg mode "$mode" --arg scope "$scope" --arg content "$content" --argjson changed "$changed" \
     --arg dry "$DRY_RUN" '
    ([.components | to_entries[] | {key, workflow: .value.workflow, bundle_component: .value.bundle_component,
        scopes: (.value.scopes // ["cloud", "onprem"]),
        selected: (if $content == "CHANGED_ONLY" then (.key | IN($changed[])) else true end)}]
     + [.full_extras[]? | {key: (.workflow | sub("\\.ya?ml$"; "")), workflow,
        bundle_component: (.bundle_component // null), scopes: ["cloud", "onprem"],
        selected: ($content == "FULL")}])
    | map(. as $c
        | (($mode == "legacy") or any($c.scopes[]; . == $scope)) as $in_scope
        | del(.scopes)
        | . + {expected: ($mode == "testing" and $c.selected and $in_scope and $c.bundle_component != null),
               action: (if ($in_scope | not) then "out-of-scope"
                        elif ($c.selected | not) then "skipped"
                        elif $dry == "true" then "dry-run" else "dispatch" end)}
        | del(.selected))
  ' "$COMPONENTS_FILE"
}

# in (job 2): TAG, BRANCH, HEAD_SHA, ROLE, BUNDLE_REPOS, RELEASE_SCOPE, PREVIOUS_STABLE_BUNDLE_TAG, DISPATCH_CONTENT,
#   COMPONENTS_FILE, CHANGED_FILE, COMMIT_LISTS_DIR, BUCKET, STATUS_DRY_RUN, DRY_RUN,
#   ABANDONED_BUNDLE (tag of the bundle the opener abandoned, empty otherwise)
# writes repos/<repo>.json, the repos.<repo> section of the global file and a trigger event;
# plus an "abandoned" event under the abandoned bundle
# shellcheck disable=SC2153 # inputs come from the environment
record() {
  local repo=${GITHUB_REPOSITORY#*/} plan run_url now
  run_url="${GITHUB_SERVER_URL:-https://github.com}/$GITHUB_REPOSITORY/actions/runs/${GITHUB_RUN_ID:-0}"
  now=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  plan=$(build_manifest testing "$RELEASE_SCOPE" "$DISPATCH_CONTENT" "$CHANGED_FILE")
  local commits='{}' f c
  if [[ -d "${COMMIT_LISTS_DIR:-}" ]]; then
    for f in "$COMMIT_LISTS_DIR"/*.txt; do
      [[ -e "$f" ]] || continue
      c=$(basename "$f" .txt)
      commits=$(jq -c --arg c "$c" --argjson n "$(grep -c . "$f" || true)" '. + {($c): $n}' <<< "$commits")
    done
  fi
  local changed='[]'
  [[ -s "$CHANGED_FILE" ]] && changed=$(jq -R -s -c 'split("\n") | map(select(length > 0))' "$CHANGED_FILE")

  local work
  work=$(mktemp -d)
  jq -n --arg bundle "$TAG" --arg repo "$repo" --arg role "$ROLE" --arg branch "$BRANCH" --arg commit "$HEAD_SHA" \
     --arg run_url "$run_url" --arg previous "$PREVIOUS_STABLE_BUNDLE_TAG" --arg content "$DISPATCH_CONTENT" \
     --argjson changed "$changed" --argjson plan "$plan" --argjson commits "$commits" --arg now "$now" \
     --arg bundle_repos "${BUNDLE_REPOS:-$repo}" '
    {schema_version: 1, bundle: $bundle, repo: $repo, role: $role, branch: $branch, commit: $commit,
     run_url: $run_url, recorded_at: $now, previous_stable_tag: $previous, dispatch_content: $content,
     bundle_repos: ($bundle_repos | split(" ") | map(select(length > 0))),
     changed: $changed,
     expected: [$plan[] | select(.expected) | .bundle_component] | unique,
     carried: [$plan[] | select(.bundle_component != null and .action == "skipped") | .bundle_component] | unique,
     dispatched: [$plan[] | select(.action == "dispatch" or .action == "dry-run") | .workflow],
     commits: $commits}' > "$work/repo.json"
  jq -c '{commit, run_url, expected, bundle_repos}' "$work/repo.json" > "$work/section.json"
  jq -n -c --arg bundle "$TAG" --arg source "$repo" --arg ts "$now" --arg commit "$HEAD_SHA" --arg run_url "$run_url" \
    '{schema_version: 1, bundle: $bundle, source: $source, subject: $source, type: "trigger",
      status: "recorded", ts: $ts, commit: $commit, run_url: $run_url}' > "$work/event.json"

  [[ "$TAG" =~ ^testing-(cloud|onprem)-(release|hotfix)-([0-9]{8})-([0-9]{2}\.[0-9]{2})-([1-9][0-9]*)$ ]] \
    || fail "Invalid testing bundle tag '$TAG'"
  local prefix abandoned_prefix=""
  prefix="$(status_root)/${BASH_REMATCH[1]}/${BASH_REMATCH[4]}/${BASH_REMATCH[2]}/${BASH_REMATCH[3]}/${BASH_REMATCH[5]}"
  if [[ -n "${ABANDONED_BUNDLE:-}" ]]; then
    [[ "$ABANDONED_BUNDLE" =~ ^testing-(cloud|onprem)-(release|hotfix)-([0-9]{8})-([0-9]{2}\.[0-9]{2})-([1-9][0-9]*)$ ]] \
      || fail "Invalid abandoned bundle tag '$ABANDONED_BUNDLE'"
    abandoned_prefix="$(status_root)/${BASH_REMATCH[1]}/${BASH_REMATCH[4]}/${BASH_REMATCH[2]}/${BASH_REMATCH[3]}/${BASH_REMATCH[5]}"
    jq -c --arg bundle "$ABANDONED_BUNDLE" '.bundle = $bundle | .type = "abandoned" | .status = "abandoned"' \
      "$work/event.json" > "$work/abandoned.json"
  fi
  {
    echo "### Bundle record: $TAG"
    echo ""
    echo "\`s3://$BUCKET/$prefix/repos/$repo.json\`"
    echo ""
    echo '```json'
    jq . "$work/repo.json"
    echo '```'
  } | summary
  jq . "$work/repo.json"

  if [[ "$DRY_RUN" == "true" ]]; then
    echo "[dry-run] would write s3://$BUCKET/$prefix/repos/$repo.json, its global section and a trigger event"
    if [[ -n "$abandoned_prefix" ]]; then
      echo "[dry-run] would write an abandoned event under s3://$BUCKET/$abandoned_prefix/events/:"
      jq . "$work/abandoned.json"
    fi
    rm -rf "$work"
    return 0
  fi
  # shellcheck source=../../actions/release-bundle-status/bundle-s3.sh
  source "$SCRIPT_DIR/../../actions/release-bundle-status/bundle-s3.sh"
  bundle_s3_check_aws
  aws s3api put-object --bucket "$BUCKET" --key "$prefix/repos/$repo.json" --body "$work/repo.json" \
    --content-type application/json > /dev/null || fail "Cannot write s3://$BUCKET/$prefix/repos/$repo.json"
  bundle_s3_put_event "$prefix/events/$(date -u +%Y%m%dT%H%M%SZ)_${repo}_trigger_${GITHUB_RUN_ID:-0}-${GITHUB_RUN_ATTEMPT:-1}.json" \
    "$work/event.json"
  if [[ -n "$abandoned_prefix" ]]; then
    bundle_s3_put_event "$abandoned_prefix/events/$(date -u +%Y%m%dT%H%M%SZ)_${repo}_abandoned_${GITHUB_RUN_ID:-0}-${GITHUB_RUN_ATTEMPT:-1}.json" \
      "$work/abandoned.json"
    echo "Recorded $ABANDONED_BUNDLE as abandoned by $repo"
  fi
  local status
  status=$(bundle_s3_update_global "$prefix/global-build-status.json" "$TAG" repos "$repo" "$work/section.json")
  echo "Recorded $repo in $TAG (bundle $status)"
  rm -rf "$work"
}

# number of workflow_dispatch runs of a workflow on a ref (0 or 1), listed up to 3 times; returns 1 if unreadable
runs_on_ref() {
  local attempt count
  for (( attempt = 1; attempt <= 3; attempt++ )); do
    if count=$(gh run list --workflow "$1" --branch "$2" --event workflow_dispatch --limit 1 \
                 --json databaseId --jq length); then
      echo "$count"
      return 0
    fi
    (( attempt < 3 )) && sleep $(( attempt * ${RETRY_DELAY:-5} ))
  done
  return 1
}

# in: MODE (legacy|testing), DISPATCH_CONTENT, BRANCH, HEAD_SHA, COMPONENTS_FILE, CHANGED_FILE, DRY_RUN, GH_TOKEN
#   testing-tag mode only: TAG, RELEASE_SCOPE
# out: manifest (path of the JSON manifest: every component and what was done with it)
# shellcheck disable=SC2153 # inputs come from the environment
dispatch() {
  local mode=${MODE:-legacy} ref=$BRANCH scope=""
  if [[ "$mode" == "testing" ]]; then
    ref=$TAG
    scope=$RELEASE_SCOPE
  fi
  local manifest="$RUNNER_TEMP/release-trigger-builds-manifest.json"
  build_manifest "$mode" "$scope" "$DISPATCH_CONTENT" "$CHANGED_FILE" | jq . > "$manifest"
  jq . "$manifest"
  out manifest "$manifest"

  {
    echo "### Dispatch ($DISPATCH_CONTENT) on $ref"
    echo ""
    echo "| component | workflow | action |"
    echo "|---|---|---|"
    jq -r '.[] | "| \(.key) | \(.workflow) | \(.action) |"' "$manifest"
  } | summary

  local workflows selected
  selected=$(jq -r '.[] | select(.action != "skipped" and .action != "out-of-scope") | .workflow' "$manifest")
  mapfile -t workflows <<< "$selected"
  [[ -n "$selected" ]] || workflows=()
  if (( ${#workflows[@]} == 0 )); then
    echo "::warning::No component to dispatch ($DISPATCH_CONTENT)"
    return 0
  fi

  # check every target at the branch head before dispatching anything; note which ones take labels
  local wf missing=()
  declare -A takes_labels=()
  for wf in "${workflows[@]}"; do
    if ! git show "$HEAD_SHA:.github/workflows/$wf" > "$RUNNER_TEMP/target-workflow.yml" 2> /dev/null; then
      missing+=("$wf (missing)")
    elif ! workflow_on "$RUNNER_TEMP/target-workflow.yml" > "$RUNNER_TEMP/target-on.json"; then
      missing+=("$wf (not valid YAML)")
    elif ! jq -e 'any(.events[]; . == "workflow_dispatch")' "$RUNNER_TEMP/target-on.json" > /dev/null; then
      missing+=("$wf (no workflow_dispatch)")
    else
      jq -e '.inputs | has("labels")' "$RUNNER_TEMP/target-on.json" > /dev/null && takes_labels[$wf]=1
    fi
  done
  if (( ${#missing[@]} > 0 )); then
    echo "::error::Not dispatchable on $ref: ${missing[*]}"
    exit 1
  fi

  local -a args
  if [[ "$DRY_RUN" == "true" ]]; then
    for wf in "${workflows[@]}"; do
      args=()
      [[ "$mode" == "testing" && -n "${takes_labels[$wf]:-}" ]] && args=(-f "labels=$LABELS_VALUE")
      echo "[dry-run] gh workflow run $wf --ref $ref${args[*]:+ ${args[*]}}"
    done
    return 0
  fi

  # dispatch everything, then fail if any dispatch failed; on a tag, a workflow already started is skipped
  # (job 2 can be re-run) and each dispatch is retried, a fresh tag can take a few seconds to be visible
  local failed=() attempt existing tries=1 ok
  [[ "$mode" == "testing" ]] && tries=3
  for wf in "${workflows[@]}"; do
    args=()
    if [[ "$mode" == "testing" ]]; then
      existing=$(runs_on_ref "$wf" "$ref") || existing=""
      if [[ ! "$existing" =~ ^[0-9]+$ ]]; then
        echo "::error::Cannot list the runs of $wf on $ref: not dispatched, to never start it twice"
        failed+=("$wf")
        continue
      fi
      if [[ "$existing" != "0" ]]; then
        echo "$wf already has a run on $ref, not dispatched again"
        continue
      fi
      [[ -n "${takes_labels[$wf]:-}" ]] && args=(-f "labels=$LABELS_VALUE")
    fi
    ok=false
    for (( attempt = 1; attempt <= tries; attempt++ )); do
      # a dispatch reported as failed may still have started the run: look before sending it again
      if [[ "$mode" == "testing" ]] && (( attempt > 1 )); then
        existing=$(runs_on_ref "$wf" "$ref") || existing=""
        if [[ ! "$existing" =~ ^[0-9]+$ ]]; then
          echo "::error::Cannot list the runs of $wf on $ref: not dispatched again, to never start it twice"
          break
        fi
        if [[ "$existing" != "0" ]]; then
          echo "$wf has a run on $ref after a failed dispatch, not dispatched again"
          ok=true
          break
        fi
      fi
      echo "Dispatching $wf on $ref${args[*]:+ ${args[*]}}"
      if gh workflow run "$wf" --ref "$ref" "${args[@]}"; then ok=true; break; fi
      (( attempt < tries )) && sleep $(( attempt * ${RETRY_DELAY:-5} ))
    done
    [[ "$ok" == "true" ]] || failed+=("$wf")
  done
  if (( ${#failed[@]} > 0 )); then
    echo "::error::Dispatch failed for: ${failed[*]}"
    exit 1
  fi
  echo "Runs: ${GITHUB_SERVER_URL:-https://github.com}/$GITHUB_REPOSITORY/actions?query=branch%3A$ref"
}

case "${1:-}" in
  prepare) prepare ;;
  validate) validate ;;
  tag) tag ;;
  mapping) mapping ;;
  record) record ;;
  dispatch) dispatch ;;
  *) echo "usage: $0 prepare|validate|tag|mapping|record|dispatch" >&2; exit 2 ;;
esac
