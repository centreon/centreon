#!/bin/sh
# Orchestrates a full "docker poller" test against the local `web` central,
# entirely over the API + the real centreon/install-poller docker flow:
#   1. log in to the Centreon API
#   2. create (or reuse) a poller-type API token
#   3. create the poller via the API, using central_address=web so the
#      generated install command already targets a name resolvable from any
#      container on the shared `centreon-poller-test` network
#   4. run the generated command with --no-start, attach the resulting
#      poller docker-compose.yaml to that shared network, then start it
set -u

apk add --no-cache curl jq bash >/dev/null

CENTRAL_BASE="http://web/centreon/api/latest"
POLLER_TOKEN_NAME="poller-container-token"

echo "== Logging in to ${CENTRAL_BASE} =="
LOGIN_RESPONSE=$(curl -s -X POST "${CENTRAL_BASE}/login" \
  -H "Content-Type: application/json" \
  -d "{\"security\":{\"credentials\":{\"login\":\"${CENTRAL_API_USERNAME}\",\"password\":\"${CENTRAL_API_PASSWORD}\"}}}")
TOKEN=$(echo "${LOGIN_RESPONSE}" | jq -r '.security.token // empty')
if [ -z "${TOKEN}" ]; then
  echo "Login failed: ${LOGIN_RESPONSE}" >&2
  exit 1
fi

echo "== Ensuring poller API token '${POLLER_TOKEN_NAME}' exists =="
TOKEN_HTTP_CODE=$(curl -s -o /tmp/token_response.json -w '%{http_code}' -X POST "${CENTRAL_BASE}/administration/tokens" \
  -H "Content-Type: application/json" \
  -H "X-AUTH-TOKEN: ${TOKEN}" \
  -d "{\"name\":\"${POLLER_TOKEN_NAME}\",\"type\":\"poller\",\"expiration_date\":null}")
case "${TOKEN_HTTP_CODE}" in
  200|201) echo "Poller token created." ;;
  *) echo "Poller token creation returned HTTP ${TOKEN_HTTP_CODE} (likely already exists), reusing '${POLLER_TOKEN_NAME}': $(cat /tmp/token_response.json)" ;;
esac

echo "== Creating poller '${POLLER_NAME}' =="
CREATE_RESPONSE=$(curl -s -X POST "${CENTRAL_BASE}/configuration/pollers" \
  -H "Content-Type: application/json" \
  -H "X-AUTH-TOKEN: ${TOKEN}" \
  -d "{\"name\":\"${POLLER_NAME}\",\"poller_type\":\"docker\",\"address\":\"${POLLER_NAME}-gorgone\",\"poller_token_name\":\"${POLLER_TOKEN_NAME}\",\"central_address\":\"web\"}")

INSTALL_CMD=$(echo "${CREATE_RESPONSE}" | jq -r '.installation_command // empty')
if [ -z "${INSTALL_CMD}" ]; then
  echo "Poller creation failed: ${CREATE_RESPONSE}" >&2
  echo "(a 409 usually means POLLER_NAME='${POLLER_NAME}' already exists — pick another POLLER_NAME or delete the existing poller first)" >&2
  exit 1
fi

echo "== Generated install command =="
echo "${INSTALL_CMD}"
echo "==============================="

WORKDIR="/workdir/${POLLER_NAME}"
mkdir -p "${WORKDIR}"
cd "${WORKDIR}" || exit 1

NO_START_CMD=$(echo "${INSTALL_CMD}" | sed 's/bash -s -- /bash -s -- --no-start /')

echo "== Fetching install.sh and generating docker-compose.yaml (--no-start) in ${WORKDIR} =="
if ! eval "${NO_START_CMD}"; then
  echo "install-poller script failed (is http://web/centreon/poller/install.sh actually served by this image?)." >&2
  echo "You can still run the command printed above manually, from any container on the 'centreon-poller-test' network." >&2
  exit 1
fi

echo "== Attaching the generated stack to the shared centreon-poller-test network =="
printf '\nnetworks:\n  default:\n    name: centreon-poller-test\n    external: true\n' >> docker-compose.yaml

echo "== Starting the poller stack =="
docker compose up -d

echo "Done. Poller '${POLLER_NAME}' should appear as running in Configuration > Pollers within ~1-2 minutes."
