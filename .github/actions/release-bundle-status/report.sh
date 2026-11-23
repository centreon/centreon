#!/usr/bin/env bash
# Writes the component file, a write-once event and the component's own section of the global file.
# Layout and schemas: delivery-tooling docs/release/testing-bundles.md
set -euo pipefail

# shellcheck source=bundle-s3.sh
source "$(dirname "$0")/bundle-s3.sh"

NOW=$(date -u +%Y-%m-%dT%H:%M:%SZ)
REPO=${GITHUB_REPOSITORY#*/}
RUN_URL="${GITHUB_SERVER_URL}/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}"
JOBS=${JOB_RESULTS:-null}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

if [[ ! "$BUNDLE" =~ $BUNDLE_TAG_REGEX ]]; then
  echo "::error::Invalid testing bundle tag '$BUNDLE'"
  exit 1
fi
SCOPE="${BASH_REMATCH[1]}" TYPE="${BASH_REMATCH[2]}" NUMBER="${BASH_REMATCH[3]}" MAJOR="${BASH_REMATCH[4]}" ITERATION="${BASH_REMATCH[5]}"
if [[ "$BUNDLE_PATH" != "bundles/$SCOPE/$MAJOR/$TYPE/$NUMBER/$ITERATION" ]]; then
  echo "::error::Bundle path '$BUNDLE_PATH' does not match the tag '$BUNDLE'"
  exit 1
fi
if [[ ! "$COMPONENT" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]]; then
  echo "::error::Invalid component name '$COMPONENT'"
  exit 1
fi
case "$DRY_RUN" in
  true) PREFIX="dry-run/$BUNDLE_PATH" ;;
  false) PREFIX=$BUNDLE_PATH ;;
  *) echo "::error::dry_run must be true or false (got '$DRY_RUN')"; exit 1 ;;
esac

bundle_s3_check_aws

# packages from the delivery manifests of the run (one directory per artifact)
: > "$WORK/packages.jsonl"
if [[ -d "$MANIFESTS_DIR" ]]; then
  while IFS= read -r -d '' manifest; do
    # shellcheck disable=SC2016 # jq variables, not shell ones
    if ! jq -c '.distrib as $d | .packages[]
          | {name, version, release, arch, distrib: $d, sha256, repository}
          | with_entries(select(.value != null))' "$manifest" >> "$WORK/packages.jsonl"; then
      echo "::error::Unreadable delivery manifest $manifest"
      exit 1
    fi
  done < <(find "$MANIFESTS_DIR" -name pulp-delivery-manifest.json -print0)
fi
PACKAGES=$(jq -s -c 'unique_by(.repository, .distrib, .name, .version, .release, .arch, .sha256)' "$WORK/packages.jsonl")
echo "$(jq length <<< "$PACKAGES") delivered package(s) found in the manifests"
if [[ "$STATE" == "success" && "$PACKAGES" == "[]" ]]; then
  echo "::warning::Successful delivery but no package found in the manifests of the run (artifact download failed?)"
fi

SECTION=$(jq -n -c \
  --arg repo "$REPO" --arg state "$STATE" --arg commit "$GITHUB_SHA" --arg run_url "$RUN_URL" \
  --argjson attempt "${GITHUB_RUN_ATTEMPT:-1}" --arg finished_at "$NOW" --argjson packages "$PACKAGES" \
  '{repo: $repo, state: $state, commit: $commit, run_url: $run_url, attempt: $attempt,
    finished_at: $finished_at, packages: $packages}')

jq -n -c --argjson section "$SECTION" --arg bundle "$BUNDLE" --arg component "$COMPONENT" --argjson jobs "$JOBS" \
  '{schema_version: 1, bundle: $bundle, component: $component} + $section + {delivery_jobs: $jobs}' \
  > "$WORK/component.json"
aws s3api put-object --bucket "$BUCKET" --key "$PREFIX/components/$COMPONENT.json" \
  --body "$WORK/component.json" --content-type application/json > /dev/null
echo "Wrote s3://$BUCKET/$PREFIX/components/$COMPONENT.json ($STATE)"

EVENT_KEY="$PREFIX/events/$(date -u +%Y%m%dT%H%M%SZ)_${COMPONENT}_delivery_${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT:-1}.json"
jq -n -c --arg bundle "$BUNDLE" --arg source "$REPO" --arg subject "$COMPONENT" --arg status "$STATE" \
  --arg ts "$NOW" --arg commit "$GITHUB_SHA" --arg run_url "$RUN_URL" \
  '{schema_version: 1, bundle: $bundle, source: $source, subject: $subject, type: "delivery",
    status: $status, ts: $ts, commit: $commit, run_url: $run_url}' > "$WORK/event.json"
bundle_s3_put_event "$EVENT_KEY" "$WORK/event.json"

# global file: replace only components.<COMPONENT>, recompute the status, write if unchanged since read
echo "$SECTION" > "$WORK/section.json"
STATUS=$(bundle_s3_update_global "$PREFIX/global-build-status.json" "$BUNDLE" components "$COMPONENT" "$WORK/section.json")
echo "Updated s3://$BUCKET/$PREFIX/global-build-status.json: $COMPONENT=$STATE, bundle $STATUS"
{
  echo "### Bundle status: $BUNDLE"
  echo ""
  echo "| component | state | packages | bundle |"
  echo "|---|---|---|---|"
  echo "| $COMPONENT | $STATE | $(jq length <<< "$PACKAGES") | $STATUS |"
} >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
