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

# Execute GraphQL, retrying transient transport failures.
# The API occasionally answers 5xx: a single failed attempt must not be reported
# to the caller as a usable answer, so exhausted retries exit non-zero.
MAX_ATTEMPTS="${XRAY_MAX_ATTEMPTS:-3}"

# Validate before any arithmetic use: bash evaluates the *contents* of a variable
# referenced inside (( )), so a malformed value would break the loop bounds.
if [[ ! "$MAX_ATTEMPTS" =~ ^[1-9][0-9]{0,2}$ ]]; then
  echo "ERROR: XRAY_MAX_ATTEMPTS must be a positive integer, got '${MAX_ATTEMPTS}'"
  exit 1
fi

RETRY_DELAYS=(5 15)
attempt=1

while :; do
  # Bound every attempt: without this a stalled connection hangs the job instead
  # of becoming the retryable failure this loop exists to absorb.
  curl_status=0
  HTTP_CURL_CALL=$(curl -sS -w "\n%{http_code}" \
    --connect-timeout 10 --max-time 60 \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer ${XRAY_TOKEN}" \
    -X POST \
    -d "{\"query\": $(jq -Rs . <<< "$QUERY"), \"variables\": ${VARIABLES_JSON}}" \
    https://xray.cloud.getxray.app/api/v2/graphql) || curl_status=$?

  HTTP_CODE=$(echo "$HTTP_CURL_CALL" | tail -n1)
  RESPONSE_JSON=$(echo "$HTTP_CURL_CALL" | sed '$d')

  retryable=""
  if ((curl_status != 0)); then
    retryable="curl exited ${curl_status}"
  elif [[ "$HTTP_CODE" =~ ^5[0-9]{2}$ || "$HTTP_CODE" == "429" ]]; then
    retryable="HTTP $HTTP_CODE"
  elif echo "$RESPONSE_JSON" | grep -q "503 ERROR"; then
    retryable="Service Unavailable (503 ERROR)"
  elif ! jq -e '.' >/dev/null 2>&1 <<<"$RESPONSE_JSON"; then
    retryable="response is not valid JSON"
  fi

  if [[ -z "$retryable" ]]; then
    break
  fi

  if ((attempt >= MAX_ATTEMPTS)); then
    echo "ERROR: Xray API call failed after $attempt attempt(s): $retryable"
    echo "Response: $RESPONSE_JSON"
    exit 1
  fi

  delay="${RETRY_DELAYS[$((attempt - 1))]:-15}"
  echo "::warning::Xray API call failed ($retryable), retrying in ${delay}s (attempt $attempt/$MAX_ATTEMPTS)"
  sleep "$delay"
  attempt=$((attempt + 1))
done

echo "HTTP_CODE: $HTTP_CODE"

# Client errors mean a malformed request or bad credentials: retrying will not help.
if [[ "$HTTP_CODE" =~ ^4[0-9]{2}$ ]]; then
  echo "ERROR: HTTP $HTTP_CODE from Xray API"
  echo "Response: $RESPONSE_JSON"
  exit 1
fi

GET_RESPONSE=$(jq -c '.' <<<"$RESPONSE_JSON")

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
