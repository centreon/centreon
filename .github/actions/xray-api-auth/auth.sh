#!/usr/bin/env bash
set -euo pipefail

CLIENT_ID="$1"
CLIENT_SECRET="$2"

MAX_RETRIES=5
RETRY_DELAY=2

attempt=0
while :; do
  response=$(curl -s -w "\n%{http_code}" -X POST \
    -H "Content-Type: application/json" \
    --data "{
      \"client_id\": \"${CLIENT_ID}\",
      \"client_secret\": \"${CLIENT_SECRET}\"
    }" \
    https://xray.cloud.getxray.app/api/v1/authenticate || true)

  http_code=$(tail -n1 <<<"$response")
  body=$(sed '$d' <<<"$response")
  [[ -z "$http_code" ]] && http_code="000"
  token=$(tr -d '"' <<<"$body")

  # Success: HTTP 200 with a non-empty token.
  if [[ "$http_code" == "200" && -n "$token" && "$token" != "null" ]]; then
    echo "$token"
    exit 0
  fi

  # Retry transient failures only: network error (000), 429, 5xx, or an empty token on 200.
  if [[ "$attempt" -lt "$MAX_RETRIES" && ( "$http_code" == "000" || "$http_code" == "429" || "$http_code" -ge 500 || ( "$http_code" == "200" && ( -z "$token" || "$token" == "null" ) ) ) ]]; then
    echo "Xray auth transient failure (HTTP $http_code), retry $((attempt + 1))/$MAX_RETRIES in ${RETRY_DELAY}s" >&2
    attempt=$((attempt + 1))
    sleep "$RETRY_DELAY"
    continue
  fi

  # Permanent failure (e.g. 4xx bad credentials) or retries exhausted.
  echo "Xray authentication failed: HTTP $http_code" >&2
  echo "$body" >&2
  exit 1
done
