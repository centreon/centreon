#!/usr/bin/env bash
# release-new guard, shared by centreon, centreon-collect and centreon-modules.
# Refuses a release when the testing packages were not built from the head of the release branch.
# usage: release-new-bundle-guard.sh mode|check
#   mode   prints and outputs "testing", "legacy" or "invalid" (testing_tag_builds on the release branch)
#   check  applies the rules below; a legacy branch is skipped. Outputs head_sha, the commit that was
#          checked: the release job refuses to tag anything else
# in: RELEASE_BRANCH, RELEASE_NUMBER, RELEASE_TYPE, RELEASE_SCOPE, GITHUB_REPOSITORY,
#     SKIP_BUNDLE_CHECK (true: report what would be refused, never fail), BUCKET
# Rules (delivery-tooling docs/release/testing-bundles.md, "release-new guard"):
#   1 latest bundle complete, built from the branch head            allow
#   2 a commit was merged after the bundle                           refuse
#   3 latest bundle incomplete                                       refuse
#   4 no bundle for this release number on this line                 refuse
#   5 legacy branch (no testing_tag_builds)                          skipped
#   6 this repository is not part of the latest bundle               refuse
#   7 a component was unchanged, so not rebuilt                      allow (the commit is compared)
#   8 the latest testing tag here carries another release number     refuse
# Also refused: an unreadable mapping, bucket or status file, and a testing-tag line whose branch is
# not named release-XX.YY-next or hotfix-XX.YY-next.
set -euo pipefail

MAPPING_PATH=.github/scripts/release/release-components.json
BUCKET=${BUCKET:-release-orchestrator}

out() { echo "$1=$2" >> "${GITHUB_OUTPUT:-/dev/null}"; }
summary() { cat >> "${GITHUB_STEP_SUMMARY:-/dev/null}"; }

# iterations (N) of a release that have files in the bucket, one per line
s3_iterations() {
  aws s3api list-objects-v2 --bucket "$BUCKET" --prefix "$1/" --delimiter / \
    --query 'CommonPrefixes[].Prefix' --output json \
    | jq -r --arg p "$1/" '(. // [])[] | ltrimstr($p) | rtrimstr("/") | select(test("^[0-9]+$"))'
}

# global file of one bundle on stdout; fails when it is missing or unreadable
s3_global() { aws s3 cp "s3://$BUCKET/$1/global-build-status.json" - 2> /dev/null; }

# mode of the release branch: testing, legacy (no mapping file, or flag false) or invalid.
# invalid = the branch or its mapping cannot be read: never treated as legacy, the check refuses.
branch_mode() {
  local mapping flag
  git rev-parse -q --verify "origin/$RELEASE_BRANCH^{commit}" > /dev/null 2>&1 || { echo invalid; return; }
  git cat-file -e "origin/$RELEASE_BRANCH:$MAPPING_PATH" 2> /dev/null || { echo legacy; return; }
  mapping=$(git show "origin/$RELEASE_BRANCH:$MAPPING_PATH" 2> /dev/null) || { echo invalid; return; }
  flag=$(jq -r 'if type == "object" then (.testing_tag_builds // false) else "invalid" end' <<< "$mapping" 2> /dev/null) \
    || { echo invalid; return; }
  case "$flag" in
    true) echo testing ;;
    false) echo legacy ;;
    *) echo invalid ;;
  esac
}

# most recently created testing tag of the line in this repository (empty when there is none)
latest_line_tag() {
  git for-each-ref --sort=-version:refname --sort=-creatordate \
    --format='%(refname:short)' "refs/tags/testing-$RELEASE_SCOPE-$RELEASE_TYPE-*-$1-*" \
    | grep -E "^testing-$RELEASE_SCOPE-$RELEASE_TYPE-[0-9]{8}-${1//./\\.}-[0-9]+$" | head -n 1 || true
}

report() { # verdict, rule, message
  {
    echo "### Testing bundle check: $1"
    echo
    echo "| | |"
    echo "|---|---|"
    echo "| Release | \`$RELEASE_SCOPE\` \`$RELEASE_TYPE\` \`$RELEASE_NUMBER\` on \`$RELEASE_BRANCH\` |"
    echo "| Branch head | \`${HEAD_SHA:-unknown}\` |"
    echo "| Latest testing tag here | \`${LINE_TAG:-none}\` |"
    echo "| Bundle checked | \`${BUNDLE:-none}\` (status \`${BUNDLE_STATUS:-unknown}\`, commit \`${BUNDLE_COMMIT:-none}\`) |"
    echo "| Rule | $2 |"
    echo
    echo "$3"
  } | summary
}

refuse() { # rule, message
  if [[ "${SKIP_BUNDLE_CHECK:-false}" == "true" ]]; then
    echo "::warning::Testing bundle check SKIPPED on request. It would have refused (rule $1): $2"
    report "skipped on request" "$1" "**Would have been refused:** $2"
    exit 0
  fi
  report refused "$1" "$2"
  echo "::error::Testing bundle check (rule $1): $2" >&2
  exit 1
}

check() {
  local repo=${GITHUB_REPOSITORY#*/} major prefix iterations latest global mode
  mode=$(branch_mode)
  if [[ "$mode" == "legacy" ]]; then
    echo "Branch $RELEASE_BRANCH is in legacy mode (no testing_tag_builds): testing bundle check skipped (rule 5)"
    echo "### Testing bundle check: skipped, \`$RELEASE_BRANCH\` is a legacy line (rule 5)" | summary
    return 0
  fi

  # From here the release job tags this commit only, whatever happens below (override included):
  # it fails if the branch moved in between.
  # A branch that cannot be resolved has nothing to pin: this fails even with skip_bundle_check.
  if ! HEAD_SHA=$(git rev-parse -q --verify "origin/$RELEASE_BRANCH^{commit}" 2> /dev/null); then
    echo "::error::Testing bundle check: origin/$RELEASE_BRANCH cannot be resolved, nothing can be released (skip_bundle_check does not apply)" >&2
    exit 1
  fi
  out head_sha "$HEAD_SHA"

  [[ "$mode" == "testing" ]] \
    || refuse "mapping" "Cannot read testing_tag_builds in $MAPPING_PATH on $RELEASE_BRANCH: the branch or the file is unreadable or malformed."

  # a line built from testing tags is always release-XX.YY-next or hotfix-XX.YY-next: no bundle can
  # match another name, so it is refused rather than released unchecked
  [[ "$RELEASE_BRANCH" =~ ^(release|hotfix)-([0-9]{2}\.[0-9]{2})-next$ ]] \
    || refuse "branch" "$RELEASE_BRANCH is built from testing tags but is not named release-XX.YY-next or hotfix-XX.YY-next: no testing bundle can be matched to it."
  major=${BASH_REMATCH[2]}

  LINE_TAG=$(latest_line_tag "$major")
  prefix="bundles/$RELEASE_SCOPE/$major/$RELEASE_TYPE/$RELEASE_NUMBER"

  # rule 8: the packages in testing come from the latest testing tag of the line in this repository
  if [[ -n "$LINE_TAG" && "$LINE_TAG" != testing-"$RELEASE_SCOPE"-"$RELEASE_TYPE"-"$RELEASE_NUMBER"-* ]]; then
    refuse 8 "The latest testing tag of this line in $repo is \`$LINE_TAG\`: it carries another release number than $RELEASE_NUMBER. Give release-new the release number of the bundle to promote."
  fi

  iterations=$(s3_iterations "$prefix") \
    || refuse "bucket" "Cannot list s3://$BUCKET/$prefix/ (release-new must be dispatched from develop to read the bundles). Retry, or tick skip_bundle_check after checking the bundle by hand."
  latest=$(sort -n <<< "$iterations" | tail -n 1)
  [[ -n "$latest" ]] \
    || refuse 4 "No testing bundle for release number $RELEASE_NUMBER on $RELEASE_SCOPE $major $RELEASE_TYPE. Check the release number, or run release-trigger-builds first."

  BUNDLE="testing-$RELEASE_SCOPE-$RELEASE_TYPE-$RELEASE_NUMBER-$major-$latest"
  if ! global=$(s3_global "$prefix/$latest") || ! jq -e 'type == "object"' > /dev/null 2>&1 <<< "$global"; then
    refuse "bucket" "Cannot read the status of \`$BUNDLE\` (s3://$BUCKET/$prefix/$latest/global-build-status.json). Retry, or tick skip_bundle_check after checking the bundle by hand."
  fi
  BUNDLE_STATUS=$(jq -r '.status // "unknown"' <<< "$global")
  BUNDLE_COMMIT=$(jq -r --arg r "$repo" '.repos[$r].commit // empty' <<< "$global")

  [[ "$BUNDLE_STATUS" == "complete" ]] \
    || refuse 3 "The latest bundle \`$BUNDLE\` is $BUNDLE_STATUS: $(jq -r '[.components // {} | to_entries[] | select(.value.state != "success") | "\(.key)=\(.value.state)"] | if length > 0 then join(", ") else "a declared repository has not reported or a component has no state yet" end' <<< "$global"). Fix it and run a new bundle."

  [[ -n "$BUNDLE_COMMIT" ]] \
    || refuse 6 "$repo is not part of the latest bundle \`$BUNDLE\` (repositories: $(jq -r '.repos // {} | keys | join(", ")' <<< "$global")). Nothing of this repository was built to promote: check the repository and the release number."

  [[ "$BUNDLE_COMMIT" == "$HEAD_SHA" ]] \
    || refuse 2 "\`$BUNDLE\` was built from ${BUNDLE_COMMIT:0:10}, but $RELEASE_BRANCH is now at ${HEAD_SHA:0:10}: commits were merged after the bundle (any commit counts, even one that changes no package). Run a new bundle, then release."

  echo "Testing bundle check passed: $BUNDLE is complete and was built from $HEAD_SHA"
  report passed "1" "\`$BUNDLE\` is complete and was built from the head of \`$RELEASE_BRANCH\`."
}

main() {
  case "${1:-}" in
    mode) local mode; mode=$(branch_mode); echo "Mode of $RELEASE_BRANCH: $mode"; out mode "$mode" ;;
    check) check ;;
    *) echo "usage: $0 mode|check" >&2; exit 2 ;;
  esac
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then main "$@"; fi
