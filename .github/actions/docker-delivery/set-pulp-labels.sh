#!/usr/bin/env bash
# Sets pulp_labels on the container push distribution created by docker push.
# Expected env vars: PULP_URL, PULP_TOKEN,
#                    BASE_PATH, MODULE, OS, STABILITY, TAG, ADDITIONAL_TAG, PLATFORMS
set -euo pipefail

TAGS="$TAG"
if [[ -n "$ADDITIONAL_TAG" ]]; then
  TAGS="$TAGS $ADDITIONAL_TAG"
fi

ARCHITECTURES=$(echo "$PLATFORMS" | tr ',' '\n' | sed 's|linux/||' | tr '\n' ' ' | sed 's/ $//')

HREF=$(curl -fsSL -H "Authorization: Bearer $PULP_TOKEN" \
  -G --data-urlencode "base_path=$BASE_PATH" \
  "$PULP_URL/api/v3/distributions/container/container/" \
  | jq -r '.results[0].pulp_href')

if [[ -z "$HREF" || "$HREF" == "null" ]]; then
  echo "::error::Distribution not found for base_path=$BASE_PATH"
  exit 1
fi

RESPONSE=$(curl -sS -w "\nHTTP_STATUS:%{http_code}" -X PATCH -H "Authorization: Bearer $PULP_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"pulp_labels\": {\"module\": \"$MODULE\", \"os\": \"$OS\", \"stability\": \"$STABILITY\", \"tags\": \"$TAGS\", \"architectures\": \"$ARCHITECTURES\"}}" \
  "$PULP_URL$HREF")

HTTP_STATUS=$(echo "$RESPONSE" | grep -o "HTTP_STATUS:[0-9]*" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/HTTP_STATUS:/d')
echo "Pulp response ($HTTP_STATUS): $BODY"

if [[ "$HTTP_STATUS" != "200" && "$HTTP_STATUS" != "202" ]]; then
  echo "::error::Failed to set Pulp labels (HTTP $HTTP_STATUS)"
  exit 1
fi
