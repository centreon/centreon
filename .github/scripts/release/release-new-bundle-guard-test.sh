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
echo '[1, 2' > "$MAPPING_PATH"; git add -A; git commit -q -m malformed; branch_at_head release-29.94-next
echo '["testing_tag_builds"]' > "$MAPPING_PATH"; git add -A; git commit -q -m notobject; branch_at_head release-29.93-next
C7=$(git rev-parse HEAD)
commit custom true; branch_at_head release-29.97-custom
commit customlegacy false; branch_at_head release-29.96-custom

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
if grep -q "Testing bundle check: passed" "$GITHUB_STEP_SUMMARY" && grep -q "head_sha=$C2" "$GITHUB_OUTPUT"; then
  PASS=$((PASS + 1)); echo "PASS    1 summary and head_sha output written"
else
  FAILED=$((FAILED + 1)); echo "FAILED  1 summary and head_sha output written"
fi

# rule 7 has no code of its own: only the repository commit is compared, so a bundle that lists a single
# component of the repository passes like any other
bundle $L/20291200 1 complete "{\"centreon\": \"$C2\"}" '{"centreon-dsm": {"state": "success"}}'
run_case "7 unchanged component not rebuilt" 0 "check passed" release-29.97-next 20291200 release cloud

# rule 4 again: a wrong number without any bundle, while the line has tags of another number -> rule 8
run_case "8 wrong number, latest tag carries another" 1 "(rule 8)" release-29.97-next 20291100 release cloud

# rule 2: the bundle was built from an older commit
bundle $L/20291200 1 complete "{\"centreon\": \"$C1\"}"
run_case "2 commit merged after the bundle" 1 "(rule 2)" release-29.97-next 20291200 release cloud
run_case "2 override" 0 "would have refused (rule 2)" release-29.97-next 20291200 release cloud true

# rule 3: the latest bundle is incomplete, an older one was complete
bundle $L/20291200 1 complete "{\"centreon\": \"$C2\"}"
tag_at testing-cloud-release-20291200-29.97-2 "$C2" 2029-12-02T10:00:00Z
bundle $L/20291200 2 incomplete "{\"centreon\": \"$C2\"}" '{"centreon-dsm": {"state": "failed"}, "centreon-web": {"state": "success"}}'
run_case "3 latest bundle incomplete" 1 "centreon-dsm=failed" release-29.97-next 20291200 release cloud
run_case "3 override" 0 "would have refused (rule 3)" release-29.97-next 20291200 release cloud true
bundle $L/20291200 2 incomplete "{\"centreon\": \"$C2\"}" '{"centreon-web": {"state": "success"}}'
run_case "3 incomplete with no failed component" 1 "has not reported" release-29.97-next 20291200 release cloud

# iterations are compared as numbers: 10 is later than 9
bundle $L/20291200 9 incomplete "{\"centreon\": \"$C2\"}"
bundle $L/20291200 10 complete "{\"centreon\": \"$C2\"}"
tag_at testing-cloud-release-20291200-29.97-10 "$C2" 2029-12-03T10:00:00Z
run_case "1 iteration 10 is later than 9" 0 "29.97-10 is complete" release-29.97-next 20291200 release cloud

# rule 6: the latest bundle does not contain this repository
bundle $L/20291200 11 complete '{"centreon-collect": "abc"}'
run_case "6 repository not in the latest bundle" 1 "(rule 6)" release-29.97-next 20291200 release cloud
run_case "6 override" 0 "would have refused (rule 6)" release-29.97-next 20291200 release cloud true
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

run_case "4 override" 0 "would have refused (rule 4)" release-29.97-next 20291201 release onprem true

# a mapping that cannot be read is never taken for a legacy line
run_case "malformed mapping is refused" 1 "(rule mapping)" release-29.94-next 20291200 release cloud
run_case "mapping that is not an object is refused" 1 "(rule mapping)" release-29.93-next 20291200 release cloud
run_case "unknown branch is refused" 1 "cannot be resolved" release-29.92-next 20291200 release cloud
: > "$GITHUB_OUTPUT"
run_case "unknown branch is refused even with the override" 1 "cannot be resolved" release-29.92-next 20291200 release cloud true
if [[ ! -s "$GITHUB_OUTPUT" ]]; then
  PASS=$((PASS + 1)); echo "PASS    unknown branch: no head_sha output"
else
  FAILED=$((FAILED + 1)); echo "FAILED  unknown branch: no head_sha output"; cat "$GITHUB_OUTPUT"
fi

# the override never drops the pin of the tagged commit
: > "$GITHUB_OUTPUT"
run_case "malformed mapping, override" 0 "would have refused (rule mapping)" release-29.93-next 20291200 release cloud true
if grep -qx "head_sha=$C7" "$GITHUB_OUTPUT"; then
  PASS=$((PASS + 1)); echo "PASS    malformed mapping, override: head_sha still output"
else
  FAILED=$((FAILED + 1)); echo "FAILED  malformed mapping, override: head_sha still output"; cat "$GITHUB_OUTPUT"
fi
: > "$GITHUB_OUTPUT"
run_case "5 legacy" 0 "legacy mode" release-29.96-next 20291200 release cloud
if [[ ! -s "$GITHUB_OUTPUT" ]]; then
  PASS=$((PASS + 1)); echo "PASS    5 legacy: no head_sha output"
else
  FAILED=$((FAILED + 1)); echo "FAILED  5 legacy: no head_sha output"; cat "$GITHUB_OUTPUT"
fi

# rule 5: legacy branches
run_case "5 flag false" 0 "legacy mode" release-29.96-next 20291200 release cloud
run_case "5 no mapping file" 0 "legacy mode" release-29.95-next 20291200 release cloud
run_case "5 legacy branch with another name" 0 "legacy mode" release-29.96-custom 20291200 release cloud

# a testing-tag line with another name is refused, never released unchecked
run_case "testing-tag branch with another name is refused" 1 "(rule branch)" release-29.97-custom 20291200 release cloud

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
run_case "status file missing, override" 0 "SKIPPED on request" release-29.97-next 20291201 release cloud true
echo '[]' > "$S3/bundles/$L/20291201/2/global-build-status.json"
run_case "status file that is not an object is refused" 1 "Cannot read the status" release-29.97-next 20291201 release cloud
rm -rf "$S3/bundles/$L/20291201/2"

# override: reports what would have been refused, never fails
run_case "override on a refusal" 0 "would have refused (rule 8)" release-29.97-next 20291200 release cloud true
run_case "override on a passing check" 0 "check passed" release-29.97-next 20291201 release cloud true

# mode
for pair in release-29.97-next:testing release-29.96-next:legacy release-29.95-next:legacy release-29.94-next:invalid release-29.92-next:invalid; do
  got=$(RELEASE_BRANCH=${pair%%:*} bash "$SCRIPT_DIR/release-new-bundle-guard.sh" mode)
  if [[ "$got" == *": ${pair##*:}" ]]; then PASS=$((PASS + 1)); echo "PASS    mode ${pair%%:*}"; else FAILED=$((FAILED + 1)); echo "FAILED  mode ${pair%%:*}: $got"; fi
done

echo "$PASS passed, $FAILED failed"
[[ $FAILED -eq 0 ]]
