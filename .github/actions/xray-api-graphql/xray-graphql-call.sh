#!/usr/bin/env bash
set -euo pipefail

# Validate required environment variables
[[ -z "${GRAPHQL_FILE:-}" ]] && { echo "Missing GRAPHQL_FILE environment variable"; exit 1; }
[[ -z "${INPUT_VARIABLES:-}" ]] && { echo "Missing INPUT_VARIABLES environment variable"; exit 1; }
[[ -z "${XRAY_TOKEN:-}" ]] && { echo "Missing XRAY_TOKEN environment variable"; exit 1; }

echo "RAW INPUT_VARIABLES: $INPUT_VARIABLES"

# Parse input variables
PROJECT_KEY=$(jq -r '.projectKey // empty' <<<"$INPUT_VARIABLES")
SUMMARY=$(jq -r '.summary // empty' <<<"$INPUT_VARIABLES")
DESCRIPTION=$(jq -r '.description // empty' <<<"$INPUT_VARIABLES")
LIMIT=$(jq -r '.limit // 20' <<<"$INPUT_VARIABLES")
LABELS=$(jq -r '.labels // empty' <<<"$INPUT_VARIABLES")

# Determine operation from file name
OPERATION=$(basename "$GRAPHQL_FILE" .graphql)

# Build variables according to GraphQL operation.
# Test Executions are created at import time by the REST multipart endpoint
# (and linked to the Test Plan via xrayFields.testPlanKey), so only the
# Test Plan get-or-create operations go through GraphQL.
case "$OPERATION" in
  "get-test-plan")
    VARIABLES_JSON=$(jq -n \
      --arg jql "project = ${PROJECT_KEY} AND summary ~ '${SUMMARY}'" \
      --argjson limit "$LIMIT" \
      '{ jql: $jql, limit: $limit }')
    ;;
  "create-test-plan")
    VARIABLES_JSON=$(jq -n \
      --arg projectKey "$PROJECT_KEY" \
      --arg summary "$SUMMARY" \
      --arg description "$DESCRIPTION" \
      --argjson limit "$LIMIT" \
      --argjson labels "$LABELS" \
      '{ projectKey: $projectKey, summary: $summary, description: $description, limit: $limit, labels: $labels }')
    ;;
  *)
    echo "Unknown operation: $OPERATION"
    exit 1
    ;;
esac

echo "GRAPHQL VARIABLES: $VARIABLES_JSON"

# Read query
QUERY=$(<"$GRAPHQL_FILE")

# Execute GraphQL
HTTP_CURL_CALL=$(curl -s -w "\n%{http_code}" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $XRAY_TOKEN" \
  -X POST \
  -d "{\"query\": $(jq -Rs . <<< "$QUERY"), \"variables\": $VARIABLES_JSON}" \
  https://xray.cloud.getxray.app/api/v2/graphql)

# Extract HTTP code and response body
HTTP_CODE=$(echo "$HTTP_CURL_CALL" | tail -n1)
RESPONSE_JSON=$(echo "$HTTP_CURL_CALL" | sed '$d')

echo "HTTP_CODE: $HTTP_CODE"

# Check for HTTP errors (4xx or 5xx)
if [[ "$HTTP_CODE" =~ ^[45][0-9]{2}$ ]]; then
  echo "ERROR: HTTP $HTTP_CODE from Xray API"
  echo "Response: $RESPONSE_JSON"
  exit 1
fi

# Check for 503 error in response
if echo "$RESPONSE_JSON" | grep -q "503 ERROR"; then
  echo "Service Unavailable (503 ERROR) from Xray API"
  exit 1
fi

# Validate + compact JSON
if ! GET_RESPONSE=$(jq -c '.' <<<"$RESPONSE_JSON" 2>/dev/null); then
  echo "ERROR: Response is not valid JSON"
  echo "$RESPONSE_JSON"
  exit 1
fi

# GraphQL returns HTTP 200 even when the query fails; surface those errors here.
graphql_errors=$(jq -c '.errors // empty' <<<"$GET_RESPONSE")
if [[ -n "$graphql_errors" ]]; then
  echo "ERROR: GraphQL response contains errors"
  echo "$graphql_errors"
  exit 1
fi

# Write output correctly
{
  echo "response<<EOF"
  echo "$GET_RESPONSE"
  echo "EOF"
} >> "$GITHUB_OUTPUT"
