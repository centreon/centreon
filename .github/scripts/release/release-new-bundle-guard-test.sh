#!/usr/bin/env bash
set -euo pipefail

# Tests the rules of release-new-bundle-guard.sh on a throwaway git repository and fixture bundle
# files, without the network. Run: bash .github/scripts/release/release-new-bundle-guard-test.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source-path=SCRIPTDIR source=release-new-bundle-guard.sh
source "$SCRIPT_DIR/release-new-bundle-guard.sh"
set +e

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
S3="$WORK/s3"
REPO_DIR="$WORK/repo"
export GITHUB_REPOSITORY=centreon/centreon GITHUB_STEP_SUMMARY="$WORK/summary" GITHUB_OUTPUT="$WORK/output"
export GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@example.test GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@example.test

# Stand in for the bucket: a directory per bundle, S3_DOWN makes every call fail.
S3_DOWN=false
s3_iterations() {
  [[ "$S3_DOWN" == "false" ]] || return 1
  [[ -d "$S3/$1" ]] || return 0
  find "$S3/$1" -mindepth 1 -maxdepth 1 -type d -printf '%f\n'
}
s3_global() { [[ "$S3_DOWN" == "false" ]] && cat "$S3/$1/global-build-status.json" 2> /dev/null; }

git init -q "$REPO_DIR"
cd "$REPO_DIR" || exit 1
mkdir -p .github/scripts/release
commit() { # message, flag (true|false|none)
  case "$2" in
    none) rm -f "$MAPPING_PATH" ;;
    *) echo "{\"testing_tag_builds\": $2}" > "$MAPPING_PATH" ;;
  esac
  echo "$1" > file; git add -A; git commit -q -m "$1"
}
branch_at_head() { git update-ref "refs/remotes/origin/$1" HEAD; }
tag_at() { # tag, commit, date
  GIT_COMMITTER_DATE="$3" git tag -a "$1" -m "$1" "$2"
}
bundle() { # scope/major/type/number, N, status, {repo: commit, ...} as jq object, [components json]
  mkdir -p "$S3/bundles/$1/$2"
  jq -n --arg s "$3" --argjson r "$4" --argjson c "${5:-{\}}" \
    '{status: $s, repos: ($r | map_values({commit: .})), components: $c}' \
    > "$S3/bundles/$1/$2/global-build-status.json"
}

commit first true; C1=$(git rev-parse HEAD)
commit second true; C2=$(git rev-parse HEAD)
branch_at_head release-29.97-next
commit legacy false; branch_at_head release-29.96-next
commit nomapping none; branch_at_head release-29.95-next
commit onprem true; C5=$(git rev-parse HEAD); branch_at_head hotfix-29.10-next

PASS=0; FAILED=0
# name, expected exit, expected text, then: branch number type scope [skip]
run_case() {
  local name=$1 want_rc=$2 want_text=$3 output rc
  output=$(RELEASE_BRANCH=$4 RELEASE_NUMBER=$5 RELEASE_TYPE=$6 RELEASE_SCOPE=$7 SKIP_BUNDLE_CHECK=${8:-false} \
    bash -c "$(declare -f s3_iterations s3_global); S3='$S3' S3_DOWN='$S3_DOWN'; source '$SCRIPT_DIR/release-new-bundle-guard.sh'; $(declare -f s3_iterations s3_global); check" 2>&1)
  rc=$?
  if [[ $rc -eq $want_rc && "$output" == *"$want_text"* ]]; then
    PASS=$((PASS + 1)); echo "PASS    $name"
  else
    FAILED=$((FAILED + 1)); echo "FAILED  $name (exit $rc, wanted $want_rc and '$want_text')"; sed 's/^/          /' <<< "$output"
  fi
}
L=cloud/29.97/release

# rule 4: no bundle at all
run_case "4 no bundle for the number" 1 "(rule 4)" release-29.97-next 20291200 release cloud

# rule 1: complete bundle built from the head
tag_at testing-cloud-release-20291200-29.97-1 "$C2" 2029-12-01T10:00:00Z
bundle $L/20291200 1 complete "{\"centreon\": \"$C2\", \"centreon-collect\": \"abc\"}"
run_case "1 complete, built from the head" 0 "check passed" release-29.97-next 20291200 release cloud
grep -q "Testing bundle check: passed" "$GITHUB_STEP_SUMMARY" && run_case "1 summary written" 0 "" release-29.97-next 20291200 release cloud

# rule 7: a component of the repository absent from the bundle does not matter
bundle $L/20291200 1 complete "{\"centreon\": \"$C2\"}" '{"centreon-dsm": {"state": "success"}}'
run_case "7 unchanged component not rebuilt" 0 "check passed" release-29.97-next 20291200 release cloud

# rule 4 again: a wrong number without any bundle, while the line has tags of another number -> rule 8
run_case "8 wrong number, latest tag carries another" 1 "(rule 8)" release-29.97-next 20291100 release cloud

# rule 2: the bundle was built from an older commit
bundle $L/20291200 1 complete "{\"centreon\": \"$C1\"}"
run_case "2 commit merged after the bundle" 1 "(rule 2)" release-29.97-next 20291200 release cloud

# rule 3: the latest bundle is incomplete, an older one was complete
bundle $L/20291200 1 complete "{\"centreon\": \"$C2\"}"
tag_at testing-cloud-release-20291200-29.97-2 "$C2" 2029-12-02T10:00:00Z
bundle $L/20291200 2 incomplete "{\"centreon\": \"$C2\"}" '{"centreon-dsm": {"state": "failed"}, "centreon-web": {"state": "success"}}'
run_case "3 latest bundle incomplete" 1 "centreon-dsm=failed" release-29.97-next 20291200 release cloud

# iterations are compared as numbers: 10 is later than 9
bundle $L/20291200 9 incomplete "{\"centreon\": \"$C2\"}"
bundle $L/20291200 10 complete "{\"centreon\": \"$C2\"}"
tag_at testing-cloud-release-20291200-29.97-10 "$C2" 2029-12-03T10:00:00Z
run_case "1 iteration 10 is later than 9" 0 "29.97-10 is complete" release-29.97-next 20291200 release cloud

# rule 6: the latest bundle does not contain this repository
bundle $L/20291200 11 complete '{"centreon-collect": "abc"}'
run_case "6 repository not in the latest bundle" 1 "(rule 6)" release-29.97-next 20291200 release cloud
rm -rf "$S3/bundles/$L/20291200/11"

# rule 8: a more recent testing tag of the line carries another number, even when a bundle of the given number matches
tag_at testing-cloud-release-20291201-29.97-1 "$C2" 2029-12-05T10:00:00Z
bundle $L/20291201 1 complete "{\"centreon\": \"$C2\"}"
run_case "8 later tag of another release number" 1 "(rule 8)" release-29.97-next 20291200 release cloud
run_case "1 the later release number passes" 0 "check passed" release-29.97-next 20291201 release cloud

# other lines are never considered: same major, hotfix type
run_case "4 hotfix line of the same major has no bundle" 1 "(rule 4)" release-29.97-next 20291201 hotfix cloud
# wrong scope given: no bundle there
run_case "4 wrong scope" 1 "(rule 4)" release-29.97-next 20291201 release onprem

# rule 5: legacy branches
run_case "5 flag false" 0 "legacy mode" release-29.96-next 20291200 release cloud
run_case "5 no mapping file" 0 "legacy mode" release-29.95-next 20291200 release cloud
run_case "not a release branch" 0 "nothing to check" develop 20291200 release cloud

# onprem hotfix line
tag_at testing-onprem-hotfix-20291200-29.10-1 "$C5" 2029-12-01T10:00:00Z
bundle onprem/29.10/hotfix/20291200 1 complete "{\"centreon\": \"$C5\"}"
run_case "1 onprem hotfix" 0 "check passed" hotfix-29.10-next 20291200 hotfix onprem

# bucket unreadable
S3_DOWN=true
run_case "bucket unreadable is refused" 1 "Cannot list" release-29.97-next 20291201 release cloud
run_case "bucket unreadable, override" 0 "SKIPPED on request" release-29.97-next 20291201 release cloud true
S3_DOWN=false
# unreadable status file
mkdir -p "$S3/bundles/$L/20291201/2"
run_case "status file missing is refused" 1 "Cannot read the status" release-29.97-next 20291201 release cloud
rm -rf "$S3/bundles/$L/20291201/2"

# override: reports what would have been refused, never fails
run_case "override on a refusal" 0 "would have refused (rule 8)" release-29.97-next 20291200 release cloud true
run_case "override on a passing check" 0 "check passed" release-29.97-next 20291201 release cloud true

# mode
for pair in release-29.97-next:testing release-29.96-next:legacy release-29.95-next:legacy; do
  got=$(RELEASE_BRANCH=${pair%%:*} bash "$SCRIPT_DIR/release-new-bundle-guard.sh" mode)
  if [[ "$got" == *": ${pair##*:}" ]]; then PASS=$((PASS + 1)); echo "PASS    mode ${pair%%:*}"; else FAILED=$((FAILED + 1)); echo "FAILED  mode ${pair%%:*}: $got"; fi
done

echo "$PASS passed, $FAILED failed"
[[ $FAILED -eq 0 ]]
