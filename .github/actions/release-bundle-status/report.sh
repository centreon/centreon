#!/usr/bin/env bash
# Writes the component file, a write-once event and the component's own section of the global file.
# Layout and schemas: delivery-tooling docs/release/testing-bundles.md
set -euo pipefail

MAX_ATTEMPTS=${MAX_ATTEMPTS:-8}
NOW=$(date -u +%Y-%m-%dT%H:%M:%SZ)
REPO=${GITHUB_REPOSITORY#*/}
RUN_URL="${GITHUB_SERVER_URL}/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}"
JOBS=${JOB_RESULTS:-null}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

if [[ ! "$BUNDLE" =~ ^testing-(cloud|onprem)-(release|hotfix)-([0-9]{8})-([0-9]{2}\.[0-9]{2})-([1-9][0-9]*)$ ]]; then
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

# conditional writes (--if-match) need aws cli 2.22 or later
AWS_VERSION=$(aws --version 2>&1 | sed -n 's|^aws-cli/\([0-9.]*\).*|\1|p')
if [[ "$(printf '%s\n' 2.22.0 "$AWS_VERSION" | sort -V | head -n 1)" != "2.22.0" ]]; then
  echo "::error::aws cli ${AWS_VERSION:-unknown} does not support conditional writes, 2.22 or later is needed"
  exit 1
fi

# packages from the delivery manifests of the run (one directory per artifact)
: > "$WORK/packages.jsonl"
if [[ -d "$MANIFESTS_DIR" ]]; then
  while IFS= read -r -d '' manifest; do
    # shellcheck disable=SC2016 # jq variables, not shell ones
    if ! jq -e -c '.distrib as $d | .packages[]
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
if ! aws s3api put-object --bucket "$BUCKET" --key "$EVENT_KEY" --body "$WORK/event.json" \
     --content-type application/json --if-none-match '*' > /dev/null 2> "$WORK/event.err"; then
  if grep -q -E "\((PreconditionFailed|ConditionalRequestConflict)\)" "$WORK/event.err"; then
    echo "::warning::Event $EVENT_KEY already exists, not overwritten"
  else
    cat "$WORK/event.err" >&2
    echo "::error::Cannot write s3://$BUCKET/$EVENT_KEY"
    exit 1
  fi
fi

# global file: replace only components.<COMPONENT>, recompute the status, write if unchanged since read
GLOBAL_KEY="$PREFIX/global-build-status.json"
for attempt in $(seq 1 "$MAX_ATTEMPTS"); do
  if aws s3api get-object --bucket "$BUCKET" --key "$GLOBAL_KEY" "$WORK/global.json" > "$WORK/head.json" 2> "$WORK/get.err"; then
    CONDITION=(--if-match "$(jq -r .ETag "$WORK/head.json")")
  elif grep -q -E "\(NoSuchKey\)|\(404\)" "$WORK/get.err"; then
    CONDITION=(--if-none-match '*')
    jq -n --arg bundle "$BUNDLE" --arg scope "$SCOPE" --arg type "$TYPE" --arg major "$MAJOR" \
      --arg number "$NUMBER" --argjson iteration "$ITERATION" \
      '{schema_version: 1, bundle: $bundle, scope: $scope, type: $type, major: $major,
        release_number: $number, iteration: $iteration, repos: {}, components: {}}' > "$WORK/global.json"
  else
    cat "$WORK/get.err" >&2
    echo "::error::Cannot read s3://$BUCKET/$GLOBAL_KEY"
    exit 1
  fi

  # complete once every component expected by the repositories reported success
  jq --arg component "$COMPONENT" --argjson section "$SECTION" --arg now "$(date -u +%Y-%m-%dT%H:%M:%SZ)" '
    .components[$component] = $section
    | .updated_at = $now
    | ([.repos[]?.expected[]?] | unique) as $expected
    | .status = (if ($expected | length) > 0
                   and ([$expected[] as $c | .components[$c].state] | all(. == "success"))
                 then "complete" else "incomplete" end)
  ' "$WORK/global.json" > "$WORK/global.new.json"

  if aws s3api put-object --bucket "$BUCKET" --key "$GLOBAL_KEY" --body "$WORK/global.new.json" \
       --content-type application/json "${CONDITION[@]}" > /dev/null 2> "$WORK/put.err"; then
    STATUS=$(jq -r .status "$WORK/global.new.json")
    echo "Updated s3://$BUCKET/$GLOBAL_KEY: $COMPONENT=$STATE, bundle $STATUS"
    {
      echo "### Bundle status: $BUNDLE"
      echo ""
      echo "| component | state | packages | bundle |"
      echo "|---|---|---|---|"
      echo "| $COMPONENT | $STATE | $(jq length <<< "$PACKAGES") | $STATUS |"
    } >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
    exit 0
  fi
  if grep -q -E "\((PreconditionFailed|ConditionalRequestConflict|412|409)\)" "$WORK/put.err"; then
    echo "Global file changed since it was read (attempt $attempt/$MAX_ATTEMPTS), retrying"
    sleep $((RANDOM % 4 + attempt))
    continue
  fi
  cat "$WORK/put.err" >&2
  echo "::error::Cannot write s3://$BUCKET/$GLOBAL_KEY"
  exit 1
done
echo "::error::Gave up updating s3://$BUCKET/$GLOBAL_KEY after $MAX_ATTEMPTS attempts"
exit 1
