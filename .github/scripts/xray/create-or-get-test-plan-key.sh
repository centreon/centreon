#!/bin/bash
set -euo pipefail

# Create or get the Xray TestPlan key for a given major version and OS.
#
# Expected environment variables:
#   XRAY_TOKEN      - Xray API bearer token
#   IS_NIGHTLY      - "true" or "false"
#   MAJOR_VERSION   - e.g. "24.10"
#   OS              - e.g. "alma9", "bullseye", "bookworm"
#   GITHUB_REF_NAME - current git ref name (set by GitHub Actions)
#   GITHUB_OUTPUT   - step output file (set by GitHub Actions)

# Determine the summary's prefix
summary_prefix=''
if [[ "${IS_NIGHTLY:-}" == 'true' ]]; then
  summary_prefix="NIGHTLY"
else
  echo "The github_ref_name is: $GITHUB_REF_NAME"
  case "$GITHUB_REF_NAME" in
    hotfix*)
      summary_prefix="HOTFIX"
      ;;
    release*)
      summary_prefix="RLZ"
      ;;
  esac
fi

input_summary="$summary_prefix OSS $MAJOR_VERSION"
echo "The summary to search is: $input_summary"

# Paginate through all test plans to find an existing match
start=0
test_plan_key=""

while true; do
  graphql_query='{
    "query":"query GetTestPlans($jql: String, $limit: Int!, $start: Int) { getTestPlans(jql: $jql, limit: $limit, start: $start) { total results { issueId jira(fields: [\"summary\", \"key\"]) } } }",
    "variables":{"jql": "project = MON","limit": 100, "start": '"$start"'}}'

  response=$(curl -sS -H "Content-Type: application/json" -X POST -H "Authorization: Bearer $XRAY_TOKEN" --data "$graphql_query" "https://xray.cloud.getxray.app/api/v2/graphql")

  test_plans=$(echo "$response" | jq -c '.data.getTestPlans.results[].jira')

  if [ -z "$test_plans" ]; then
    echo "No more test plans found."
    break
  fi

  echo "These are the existing TPs: $test_plans"

  while read row; do
    summary=$(echo "$row" | jq -r '.summary')
    if [[ "$summary" == "$input_summary" ]]; then
      test_plan_key=$(echo "$row" | jq -r '.key')
      echo "The test_plan_key is $test_plan_key and the summary is $summary"
      break
    fi
  done <<< "$test_plans"

  if [[ -n "$test_plan_key" ]]; then
    echo "Found existing TestPlan: $test_plan_key"
    break
  fi

  start=$((start + 100))
done

# If no matching test plan was found after full pagination, create one
if [[ -z "$test_plan_key" ]]; then
  echo "TestPlan doesn't exist yet — creating it."

  create_test_plan_mutation="{
    \"query\": \"mutation CreateTestPlan(\$testIssueIds: [String], \$jira: JSON!) { createTestPlan(testIssueIds: \$testIssueIds, jira: \$jira) { testPlan { issueId jira(fields: [\\\"key\\\"]) }warnings } }\",
    \"variables\": {
      \"testIssueIds\": [],
      \"jira\": {
        \"fields\": {
          \"summary\": \"$input_summary\",
          \"project\": { \"key\": \"MON\" }
        }
      }
    }
  }"
  create_result=$(curl -sS -H "Content-Type: application/json" -X POST -H "Authorization: Bearer $XRAY_TOKEN" -d "$create_test_plan_mutation" "https://xray.cloud.getxray.app/api/v2/graphql")
  echo "API response: $create_result"

  # Validate the creation response
  if echo "$create_result" | jq -e '(.errors // []) | length > 0' > /dev/null 2>&1; then
    echo "ERROR: GraphQL errors in create TestPlan response:" >&2
    echo "$create_result" >&2
    exit 1
  fi

  test_plan_key=$(echo "$create_result" | jq -r '.data.createTestPlan.testPlan.jira.key')

  if [[ -z "$test_plan_key" || "$test_plan_key" == "null" ]]; then
    echo "ERROR: TestPlan creation returned null key. Full response:" >&2
    echo "$create_result" >&2
    exit 1
  fi

  echo "New TP created with key: $test_plan_key"
fi

# Set the testPlanKey as an output
echo "test_plan_key_${OS}=${test_plan_key}" >> "$GITHUB_OUTPUT"