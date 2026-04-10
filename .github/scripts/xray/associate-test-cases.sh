#!/bin/bash
set -euo pipefail

# Associate Xray test cases with a test plan and test execution.
#
# Expected environment variables:
#   XRAY_TOKEN        - Xray API bearer token
#   TEST_PLAN_ID      - Xray test plan issue ID
#   TEST_EXECUTION_ID - Xray test execution issue ID
#   JIRA_USER_EMAIL   - Jira user email for API auth
#   JIRA_API_TOKEN    - Jira API token for API auth
#   GITHUB_OUTPUT     - step output file (set by GitHub Actions)
#
# Must be run from centreon/tests/rest_api/ directory (set via working-directory in workflow).

get_test_ids() {
  start=0
  test_issue_ids=()

  while true; do
      xray_graphql_getTests='{
          "query": "query getTests($jql: String, $limit: Int!, $start: Int) { getTests(jql: $jql, limit: $limit, start: $start) { total results { issueId } } }",
          "variables": {
              "jql": "reporter = \"712020:093f82f0-b0f1-4498-8369-fbe72fb50bcb\" AND project = MON AND type = \"Test\" AND testType = \"API\"",
              "limit": 100,
              "start": '$start'
          }
      }'

      response=$(curl -X POST \
          -H "Content-Type: application/json" \
          -H "Authorization: Bearer $XRAY_TOKEN" \
          --data "$xray_graphql_getTests" \
          "https://xray.cloud.getxray.app/api/v2/graphql")

      echo "Response from getTests:"
      echo "$response"

      # Parsing and processing test IDs
      current_test_issue_ids=($(echo "$response" | jq -r '.data.getTests.results[].issueId'))

      echo "Current Test Issue IDs: ${current_test_issue_ids[*]}"

      # Check if there are no more test issues
      if [ ${#current_test_issue_ids[@]} -eq 0 ]; then
          echo "No more test issues. Exiting loop."
          break
      fi

      # Concatenate the current batch of results to the overall test_issue_ids array
      test_issue_ids+=("${current_test_issue_ids[@]}")

      # Increment the start value for the next iteration
      start=$((start + 100))
  done

  # Display all retrieved test issue IDs
  echo "All Test Issue IDs: ${test_issue_ids[*]}"
}

get_test_ids

formatted_getTest_issue_ids_str="["
for issue_id in "${test_issue_ids[@]}"; do
  formatted_getTest_issue_ids_str+="\"$issue_id\","
done
formatted_getTest_issue_ids_str="${formatted_getTest_issue_ids_str%,}"
formatted_getTest_issue_ids_str+="]"
echo "$formatted_getTest_issue_ids_str"

# Display the retrieved test issue IDs
echo "Test Issue IDs: ${test_issue_ids[*]}"

# Mutation to add tests to the test plan
xray_graphql_addTestsToTestPlan='{
  "query": "mutation AddTestsToTestPlan($issueId: String!, $testIssueIds: [String]!) { addTestsToTestPlan(issueId: $issueId, testIssueIds: $testIssueIds) { addedTests warning } }",
  "variables": {
    "issueId": "'"$TEST_PLAN_ID"'",
    "testIssueIds": '"$formatted_getTest_issue_ids_str"'
  }
}'

# Execute the mutation to add tests to the test plan
response_addTestsToTestPlan=$(curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $XRAY_TOKEN" \
  --data "$xray_graphql_addTestsToTestPlan" \
  "https://xray.cloud.getxray.app/api/v2/graphql")

echo "Response from Add Tests to Test Plan:"
echo "$response_addTestsToTestPlan"

get_test_plan_issue_ids() {
  start=0
  issue=()
  api_issue_ids=()

  while true; do
      xray_graphql_getTestPlan='{
          "query": "query GetTestPlan($issueId: String, $start: Int) { getTestPlan(issueId: $issueId) { issueId tests(limit: 100, start: $start) { results { issueId testType { name } } } } }",
          "variables": {
              "issueId": "'"$TEST_PLAN_ID"'",
              "start": '$start'
          }
      }'

      response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $XRAY_TOKEN" --data "${xray_graphql_getTestPlan}" "https://xray.cloud.getxray.app/api/v2/graphql")

      echo "Response from Get Test Plan:"
      echo "$response"

      # Parsing and processing test IDs
      current_issue_ids=($(echo "$response" | jq -r '.data.getTestPlan.tests.results[] | .issueId'))
      echo "Current Issue IDs: ${current_issue_ids[*]}"

      # Concatenate the current batch of results to the overall issue_ids array
      issue=("${issue[@]}" "${current_issue_ids[@]}")

      # Parsing and processing test IDs for API tests and adding them to api_issue_ids
      current_api_issue_ids=($(echo "$response" | jq -r '.data.getTestPlan.tests.results[] | select(.testType.name == "API") | .issueId'))
      api_issue_ids=("${api_issue_ids[@]}" "${current_api_issue_ids[@]}")

      # Increment the start value for the next iteration
      start=$((start + 100))

      # Check if there are more results
      if [ -z "$response" ] || [ ${#current_issue_ids[@]} -eq 0 ]; then
          echo "No more results. Exiting loop."
          break
      fi
  done

  # Display results
  echo "API Issue IDs: ${api_issue_ids[*]}"
  echo "api-issue-ids=${api_issue_ids[*]}" >> "$GITHUB_OUTPUT"
}

get_test_plan_issue_ids
issue_ids=("${api_issue_ids[@]}")

summaries=()

for issue_id in "${issue_ids[@]}"; do
  echo "Processing issue ID: $issue_id"
  jira_issue_url="https://centreon.atlassian.net/rest/api/2/issue/$issue_id"

  response=$(curl --request GET \
    --url "$jira_issue_url" \
    --user "$JIRA_USER_EMAIL:$JIRA_API_TOKEN" \
    --header 'Accept: application/json')

  summary=$(echo "$response" | jq -r '.fields.summary')

  if [ "$response_code" -eq 404 ]; then
    echo "The issue with ID $issue_id does not exist or you do not have permission to see it."
    break
  else
    echo "The issue with ID $issue_id exists."
    summaries+=("$summary")
  fi
done

collections=($(find ./collections -type f -name "*.postman_collection.json"))
test_case_ids=()

xray_graphql_AddingTestsToTestPlan='{
  "query": "mutation AddTestsToTestPlan($issueId: String!, $testIssueIds: [String]!) { addTestsToTestPlan(issueId: $issueId, testIssueIds: $testIssueIds) { addedTests warning } }",
  "variables": {
    "issueId":"'"$TEST_PLAN_ID"'",
    "testIssueIds": []
  }
}'

existing_test_case_ids=("${issue_ids[@]}")

for collection_file in "${collections[@]}"; do
  collection_name=$(basename "$collection_file" .postman_collection.json)
  collection_name_sanitized="${collection_name//[^a-zA-Z0-9]/_}"

  if [[ " ${summaries[*]} " =~ " ${collection_name_sanitized} " ]]; then
    echo "The test case for $collection_name_sanitized already exists in the test plan."
  else
    # Adding a new test case
    response=$(curl --request POST \
      --url 'https://centreon.atlassian.net/rest/api/2/issue' \
      --user "$JIRA_USER_EMAIL:$JIRA_API_TOKEN" \
      --header 'Accept: application/json' \
      --header 'Content-Type: application/json' \
      --data '{
        "fields": {
          "project": {
            "key": "MON"
          },
          "summary": "'"$collection_name_sanitized"'",
          "components": [{"name": "centreon-web"}],
          "priority":{"name":"Low"},
          "description": "Test case for '"$collection_name_sanitized"'",
          "issuetype": {
            "name": "Test"
          }
        }
      }' \
      --max-time 20)

    if [ -z "$response" ]; then
      echo "Failed to create the test case within the specified time."
    else
      test_case_id=$(echo "$response" | jq -r '.id')

      # Checking if the test case is a new one
      if [[ ! " ${existing_test_case_ids[*]} " =~ " ${test_case_id} " ]]; then
        echo "New Test Case with ID: $test_case_id"
        summaries+=("$collection_name_sanitized")

        # Update GraphQL query to add this test to the test plan
        xray_graphql_AddingTestsToTestPlan_variables=$(echo "$xray_graphql_AddingTestsToTestPlan" | jq --arg test_case_id "$test_case_id" '.variables.testIssueIds += [$test_case_id]')

        # Execute the GraphQL mutation to update the testType only for new test cases
        testType_mutation_response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $XRAY_TOKEN" --data '{"query": "mutation { updateTestType(issueId: \"'$test_case_id'\", testType: {name: \"API\"} ) { issueId testType { name kind } } }"}' "https://xray.cloud.getxray.app/api/v2/graphql")

        # Checking if the mutation was successful
        if [ "$(echo "$testType_mutation_response" | jq -r '.data.updateTestType')" != "null" ]; then
          echo "Successfully updated testType to API for Test Case with ID: $test_case_id"
        else
          echo "Failed to update testType for Test Case with ID: $test_case_id"
        fi

        # Execute the GraphQL mutation to add tests to the test plan
        response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $XRAY_TOKEN" --data "$xray_graphql_AddingTestsToTestPlan_variables" "https://xray.cloud.getxray.app/api/v2/graphql")
      else
        echo "Test Case with ID $test_case_id already exists in the test plan."
      fi
    fi
  fi
done

get_test_plan_issue_ids
issue_list_ids=("${issue[@]}")
echo "issue_list_ids=("${issue[*]}")" >> "$GITHUB_OUTPUT"

test_issue_ids=("${issue_list_ids[@]}")
formatted_test_issue_ids_str="["
for issue_id in "${issue_list_ids[@]}"; do
  formatted_test_issue_ids_str+="\"$issue_id\","
done
formatted_test_issue_ids_str="${formatted_test_issue_ids_str%,}"
formatted_test_issue_ids_str+="]"
echo "$formatted_test_issue_ids_str"

xray_graphql_addTestsToTestExecution='{
  "query": "mutation AddTestsToTestExecution($issueId: String!, $testIssueIds: [String]) { addTestsToTestExecution(issueId: $issueId, testIssueIds: $testIssueIds) { addedTests warning } }",
  "variables": {
    "issueId": "'"$TEST_EXECUTION_ID"'",
    "testIssueIds": '$formatted_test_issue_ids_str'
  }
}'

response_addTestsToTestExecution=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $XRAY_TOKEN" --data "${xray_graphql_addTestsToTestExecution}" "https://xray.cloud.getxray.app/api/v2/graphql")

echo "Response from Add Tests to Test Execution:"
echo "$response_addTestsToTestExecution"
