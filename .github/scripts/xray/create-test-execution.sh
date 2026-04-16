#!/bin/bash
set -euo pipefail

# Create an Xray TestExecution and output its ID.
#
# Expected environment variables:
#   XRAY_TOKEN        - Xray API bearer token
#   IS_NIGHTLY        - "true" or "false"
#   MAJOR_VERSION     - e.g. "24.10"
#   MINOR_VERSION     - e.g. "3"
#   OS                - e.g. "alma9", "bullseye", "bookworm"
#   GITHUB_REF_NAME   - current git ref name (set by GitHub Actions)
#   GITHUB_REPOSITORY - owner/repo (set by GitHub Actions)
#   GITHUB_RUN_ID     - workflow run ID (set by GitHub Actions)
#   GITHUB_OUTPUT     - step output file (set by GitHub Actions)

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

current_date=$(date +'%d/%m/%Y')

workflow_run_url="https://github.com/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}"

input_summary="$summary_prefix WEB ${MAJOR_VERSION}.${MINOR_VERSION} $current_date"
echo "The summary of the TE is: $input_summary"

linux_distribution=''
mariadb_version=''
case "$OS" in
  alma9)
    linux_distribution="ALMA9"
    mariadb_version="MARIADB_10_5"
    ;;
  bullseye)
    linux_distribution="DEBIAN11"
    mariadb_version="MARIADB_10_5"
    ;;
  bookworm)
    linux_distribution="DEBIAN12"
    mariadb_version="MARIADB_10_11"
    ;;
  *)
    echo "ERROR: Unknown OS value: '$OS'. Expected one of: alma9, bullseye, bookworm." >&2
    exit 1
    ;;
esac

xray_graphql_createTestExecution="{
  \"query\": \"mutation CreateTestExecution(\$testEnvironments: [String], \$jira: JSON!) { createTestExecution(testEnvironments: \$testEnvironments, jira: \$jira) { testExecution { issueId jira(fields: [\\\"key\\\"]) } warnings createdTestEnvironments } }\",
  \"variables\": {
    \"testEnvironments\": [\"$linux_distribution\",\"$mariadb_version\",\"CHROME\"],
    \"jira\": {
      \"fields\": {
        \"summary\": \"$input_summary\",
        \"description\": \"$workflow_run_url\",
        \"project\": { \"key\": \"MON\" },
        \"components\": [{\"name\": \"centreon-web\"}],
        \"priority\":{\"name\":\"Low\"}
      }
    }
  }
}"

echo "this is the graphql mutation : $xray_graphql_createTestExecution"

response=$(curl -sS -X POST -H "Content-Type: application/json" -H "Authorization: Bearer $XRAY_TOKEN" --data "$xray_graphql_createTestExecution" "https://xray.cloud.getxray.app/api/v2/graphql")

echo -e "Response from Create Test Execution:\n$response"

# Validate the response
if [ -z "$response" ]; then
  echo "ERROR: Empty response from Xray API." >&2
  exit 1
fi

if echo "$response" | jq -e '(.errors // []) | length > 0' > /dev/null 2>&1; then
  echo "ERROR: GraphQL errors in response:" >&2
  echo "$response" >&2
  exit 1
fi

if [ "$(echo "$response" | jq -r '.data.createTestExecution.testExecution')" = "null" ]; then
  echo "ERROR: TestExecution creation returned null. Full response:" >&2
  echo "$response" >&2
  exit 1
fi

# Extract the ID of the created TE
test_execution_id=$(echo "$response" | jq -r '.data.createTestExecution.testExecution.issueId')
echo "test_execution_id_${OS}=$test_execution_id" >> "$GITHUB_OUTPUT"

echo "TestExecution created with ID:  $test_execution_id"
