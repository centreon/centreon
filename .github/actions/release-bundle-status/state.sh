#!/usr/bin/env bash
# Delivery state of the component run from its expected delivery jobs.
# in: NEEDS (toJSON(needs)), EXPECTED_JOBS (comma-separated), RUN_CANCELLED; out: state, job_results
set -euo pipefail

if [[ -z "${EXPECTED_JOBS// /}" ]]; then
  echo "No delivery expected for this component in this scope, nothing to report"
  echo "state=" >> "$GITHUB_OUTPUT"
  exit 0
fi

JOB_RESULTS=$(jq -c --arg expected "$EXPECTED_JOBS" '
  . as $needs
  | [$expected | split(",")[] | gsub("^\\s+|\\s+$"; "") | select(length > 0)]
  | map({key: ., value: ($needs[.].result // "missing")}) | from_entries
' <<< "$NEEDS")

for job in $(jq -r 'to_entries[] | select(.value == "missing") | .key' <<< "$JOB_RESULTS"); do
  echo "::warning::Expected delivery job '$job' is not in the needs of this job, counted as failed"
done

# delivery jobs skip themselves on a cancelled run (! cancelled()), so cancellation comes from RUN_CANCELLED
STATE=$(jq -r --arg cancelled "$RUN_CANCELLED" '
  [.[]] as $r
  | if ($r | all(. == "success")) then "success"
    elif ($r | any(. == "failure" or . == "missing")) then "failed"
    elif ($cancelled == "true" or ($r | any(. == "cancelled"))) then "cancelled"
    else "failed"
    end
' <<< "$JOB_RESULTS")

echo "Delivery jobs: $JOB_RESULTS => $STATE"
{
  echo "state=$STATE"
  echo "job_results=$JOB_RESULTS"
} >> "$GITHUB_OUTPUT"
