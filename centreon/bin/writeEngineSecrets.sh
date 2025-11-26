#!/bin/bash

# Ensure positional parameters are handled correctly
INSECURE_FLAG=""
POSITIONAL=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --insecure)
      INSECURE_FLAG="--insecure"
      shift
      ;;
    *)
      POSITIONAL+=("$1")
      shift
      ;;
  esac
done

if [ "${#POSITIONAL[@]}" -ne 3 ]; then
  echo "Usage: $0 <api_base_url> <username> <password> [--insecure]"
  exit 1
fi

set -- "${POSITIONAL[@]}"

API_URL="$1"
USERNAME="$2"
PASSWORD="$3"
SECRETS_ENDPOINT="/api/latest/administration/engine/secrets"
OUTPUT_FILE="/etc/centreon-engine/engine-context.json"

# Authenticate and get token
TOKEN=$(curl -s $INSECURE_FLAG -X POST "$API_URL/api/latest/login" \
  -H "Content-Type: application/json" \
  -d "{\"security\":{\"credentials\":{\"login\":\"$USERNAME\",\"password\":\"$PASSWORD\"}}}" | \
  sed -n 's/.*"token"[ ]*:[ ]*"\([^"]*\)".*/\1/p')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
  echo "Authentication failed"
  exit 1
fi

# Fetch secrets and write to file
response=$(curl -s $INSECURE_FLAG -w "%{http_code}" -H "X-AUTH-TOKEN: $TOKEN" "$API_URL$SECRETS_ENDPOINT")

# Extract HTTP status code and body from the response
# Example of response '{"app_secret":"<secret>","salt":"<salt>"}200'
http_code="${response: -3}" # Extract last 3 characters for HTTP status code
body="${response::-3}" # Extract body by removing the last 3 characters

if [ "$http_code" = "200" ]; then
  echo "$body" > "$OUTPUT_FILE"
else
  echo "Error while retrieving secrets (HTTP $http_code): $body" >&2
  exit 1
fi
