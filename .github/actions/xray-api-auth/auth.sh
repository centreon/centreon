#!/usr/bin/env bash
set -euo pipefail

CLIENT_ID="$1"
CLIENT_SECRET="$2"

response=$(curl -s -w "\n%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  --data "{
    \"client_id\": \"${CLIENT_ID}\",
    \"client_secret\": \"${CLIENT_SECRET}\"
  }" \
  https://xray.cloud.getxray.app/api/v1/authenticate)

http_code=$(tail -n1 <<<"$response")
body=$(sed '$d' <<<"$response")

# Surface HTTP code and raw body on failure (diagnostics go to stderr, token to stdout).
if [[ "$http_code" != "200" ]]; then
  echo "Xray authentication failed: HTTP $http_code" >&2
  echo "$body" >&2
  exit 1
fi

# Xray returns the token as a JSON string: "token"
token=$(tr -d '"' <<<"$body")

if [[ -z "$token" || "$token" == "null" ]]; then
  echo "Xray authentication failed: empty token in response" >&2
  echo "$body" >&2
  exit 1
fi

# Output the token on stdout to be captured by the calling script
echo "$token"