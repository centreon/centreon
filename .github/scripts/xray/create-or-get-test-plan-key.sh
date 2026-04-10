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

# Initialize start value
start=0

# Loop to fetch all test plans
while true; do
  # Execute the GraphQL query with the current start value
  graphql_query='{
    "query":"query GetTestPlans($jql: String, $limit: Int!, $start: Int) { getTestPlans(jql: $jql, limit: $limit, start: $start) { total results { issueId jira(fields: [\"summary\", \"key\"]) } } }",
    "variables":{"jql": "project = MON","limit": 100, "start": '"$start"'}}'

  response=$(curl -sS -H "Content-Type: application/json" -X POST -H "Authorization: Bearer $XRAY_TOKEN" --data "$graphql_query" "https://xray.cloud.getxray.app/api/v2/graphql")

  # Parse the response and extract test plans
  test_plans=$(echo "$response" | jq -c '.data.getTestPlans.results[].jira')

  # Check if test_plans is empty
  if [ -z "$test_plans" ]; then
    echo "No more test plans found. Exiting loop."
    break
  fi

  echo "These are the existing TPs: $test_plans"

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

  # Extract the key of the existent test plan
  while read row; do
    summary=$(echo "$row" | jq -r '.summary')
    if [[ "$summary" == "$input_summary" ]]; then
      test_plan_key=$(echo "$row" | jq -r '.key')
      echo "The test_plan_key is $test_plan_key and the summary is $summary"
      break
    fi
  done <<< "$test_plans"

  echo "The test plan key for now is: ${test_plan_key:-}"

  # If no matching test plan was found, create one
  if [[ -z "${test_plan_key:-}" ]]; then
    echo "TestPlan doesn't exist yet"

    # Create the test plan using a GraphQL mutation
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
    echo "API response: $create_result "

    # Extract the key of the created test plan
    test_plan_key=$(echo "$create_result" | jq -r '.data.createTestPlan.testPlan.jira.key')
    echo "New TP created with key: $test_plan_key"
  fi

  # Update start value for the next iteration
  start=$((start + 100))
done

# Set the testPlanKey as an output
echo "test_plan_key_${OS}=${test_plan_key}" >> "$GITHUB_OUTPUT"
