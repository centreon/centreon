# Bundle status files in S3, shared by report.sh (component final jobs) and release-trigger-builds.
# Sourced, not executed. Needs: BUCKET, aws cli 2.22+ (conditional writes), jq.
# Layout and schemas: delivery-tooling docs/release/testing-bundles.md

BUNDLE_TAG_REGEX='^testing-(cloud|onprem)-(release|hotfix)-([0-9]{8})-([0-9]{2}\.[0-9]{2})-([1-9][0-9]*)$'

# conditional writes (--if-match / --if-none-match) need aws cli 2.22 or later
bundle_s3_check_aws() {
  local version
  version=$(aws --version 2>&1 | sed -n 's|^aws-cli/\([0-9.]*\).*|\1|p')
  if [[ "$(printf '%s\n' 2.22.0 "$version" | sort -V | head -n 1)" != "2.22.0" ]]; then
    echo "::error::aws cli ${version:-unknown} does not support conditional writes, 2.22 or later is needed"
    return 1
  fi
}

# usage: bundle_s3_put_event <key> <event json file>; written once, an existing event is kept
bundle_s3_put_event() {
  local key=$1 file=$2 err
  err=$(mktemp)
  if ! aws s3api put-object --bucket "$BUCKET" --key "$key" --body "$file" \
       --content-type application/json --if-none-match '*' > /dev/null 2> "$err"; then
    if grep -q -E "\((PreconditionFailed|ConditionalRequestConflict)\)" "$err"; then
      echo "::warning::Event $key already exists, not overwritten"
    else
      cat "$err" >&2
      rm -f "$err"
      echo "::error::Cannot write s3://$BUCKET/$key"
      return 1
    fi
  fi
  rm -f "$err"
}

# usage: bundle_s3_update_global <global key> <bundle tag> <components|repos> <name> <section json file>
# Replaces only .<kind>.<name>, recomputes the status, and writes only if nobody wrote in between
# (If-Match, retried). Prints the bundle status (complete|incomplete) on stdout.
bundle_s3_update_global() {
  local key=$1 bundle=$2 kind=$3 name=$4 section_file=$5
  local work attempt condition max=${MAX_ATTEMPTS:-8}
  [[ "$bundle" =~ $BUNDLE_TAG_REGEX ]] || { echo "::error::Invalid testing bundle tag '$bundle'" >&2; return 1; }
  local scope=${BASH_REMATCH[1]} type=${BASH_REMATCH[2]} number=${BASH_REMATCH[3]}
  local major=${BASH_REMATCH[4]} iteration=${BASH_REMATCH[5]}
  work=$(mktemp -d)
  for attempt in $(seq 1 "$max"); do
    if aws s3api get-object --bucket "$BUCKET" --key "$key" "$work/global.json" > "$work/head.json" 2> "$work/get.err"; then
      condition=(--if-match "$(jq -r .ETag "$work/head.json")")
    elif grep -q -E "\(NoSuchKey\)|\(404\)" "$work/get.err"; then
      condition=(--if-none-match '*')
      jq -n --arg bundle "$bundle" --arg scope "$scope" --arg type "$type" --arg major "$major" \
        --arg number "$number" --argjson iteration "$iteration" \
        '{schema_version: 1, bundle: $bundle, scope: $scope, type: $type, major: $major,
          release_number: $number, iteration: $iteration, repos: {}, components: {}}' > "$work/global.json"
    else
      cat "$work/get.err" >&2
      rm -rf "$work"
      echo "::error::Cannot read s3://$BUCKET/$key" >&2
      return 1
    fi

    # complete once every component expected by the repositories reported success
    jq --arg kind "$kind" --arg name "$name" --slurpfile section "$section_file" \
       --arg now "$(date -u +%Y-%m-%dT%H:%M:%SZ)" '
      .[$kind][$name] = $section[0]
      | .updated_at = $now
      | ([.repos[]?.expected[]?] | unique) as $expected
      | .status = (if ($expected | length) > 0
                     and ([$expected[] as $c | .components[$c].state] | all(. == "success"))
                   then "complete" else "incomplete" end)
    ' "$work/global.json" > "$work/global.new.json"

    if aws s3api put-object --bucket "$BUCKET" --key "$key" --body "$work/global.new.json" \
         --content-type application/json "${condition[@]}" > /dev/null 2> "$work/put.err"; then
      jq -r .status "$work/global.new.json"
      rm -rf "$work"
      return 0
    fi
    if grep -q -E "\((PreconditionFailed|ConditionalRequestConflict|412|409)\)" "$work/put.err"; then
      echo "Global file changed since it was read (attempt $attempt/$max), retrying" >&2
      sleep $((RANDOM % 4 + attempt))
      continue
    fi
    cat "$work/put.err" >&2
    rm -rf "$work"
    echo "::error::Cannot write s3://$BUCKET/$key" >&2
    return 1
  done
  rm -rf "$work"
  echo "::error::Gave up updating s3://$BUCKET/$key after $max attempts" >&2
  return 1
}

# usage: bundle_s3_get_global <global key> <output file>
# returns 0 when read, 3 when the file doesn't exist, 1 on any other error
bundle_s3_get_global() {
  local key=$1 out=$2 err
  err=$(mktemp)
  if aws s3api get-object --bucket "$BUCKET" --key "$key" "$out" > /dev/null 2> "$err"; then
    rm -f "$err"
    return 0
  fi
  if grep -q -E "\(NoSuchKey\)|\(404\)" "$err"; then
    rm -f "$err"
    return 3
  fi
  cat "$err" >&2
  rm -f "$err"
  return 1
}
