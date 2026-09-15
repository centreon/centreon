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
#   5. wait for the poller's gorgone to complete its first successful ping to
#      central, then trigger a generate-and-reload so the poller actually
#      gets its monitoring configuration and centengine restarts
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
POLLER_ID=$(echo "${CREATE_RESPONSE}" | jq -r '.id // empty')
if [ -z "${INSTALL_CMD}" ] || [ -z "${POLLER_ID}" ]; then
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

echo "== Waiting for the poller's gorgone to complete its first successful ping to central =="
PING_TIMEOUT=180
PING_INTERVAL=5
i=0
until docker compose logs gorgone 2>/dev/null | grep -q '"message":"ping ok"'; do
  i=$((i + 1))
  if [ "${i}" -ge "${PING_TIMEOUT}" ]; then
    echo "Timed out after $((PING_TIMEOUT * PING_INTERVAL / 60)) minutes waiting for a first ping from the poller." >&2
    echo "The poller stack is still up in ${WORKDIR} — check 'docker compose logs gorgone' there, then re-run manually once it connects:" >&2
    echo "  curl -X GET -H \"X-AUTH-TOKEN: <token>\" ${CENTRAL_BASE}/configuration/monitoring-servers/${POLLER_ID}/generate-and-reload" >&2
    echo "  docker compose --project-directory ${WORKDIR} exec gorgone sudo systemctl restart centengine" >&2
    exit 1
  fi
  sleep "${PING_INTERVAL}"
done
echo "First ping received."

echo "== Generating and reloading the monitoring configuration for poller '${POLLER_NAME}' (ID: ${POLLER_ID}) =="
GEN_HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' -m 120 \
  -H "X-AUTH-TOKEN: ${TOKEN}" \
  "${CENTRAL_BASE}/configuration/monitoring-servers/${POLLER_ID}/generate-and-reload")
if [ "${GEN_HTTP_CODE}" = "204" ]; then
  echo "Configuration generated and exported."
else
  echo "Warning: generate-and-reload returned HTTP ${GEN_HTTP_CODE} (the API token may have expired during the wait — re-run it manually with a fresh token if needed)." >&2
fi

# generate-and-reload only exports the configuration; it does not restart
# centengine. On this split-container poller, centengine and gorgone are two
# separate containers, so the restart has to go through gorgone's own
# systemctl shim, which relays it to the centengine container over gRPC.
# Gorgone doesn't run as root, hence the sudo.
echo "== Restarting centengine on the poller (via gorgone) =="
if ! docker compose exec -T gorgone sudo systemctl restart centengine; then
  echo "Warning: failed to restart centengine via gorgone — restart it manually:" >&2
  echo "  docker compose --project-directory ${WORKDIR} exec gorgone sudo systemctl restart centengine" >&2
fi

echo "Done. Poller '${POLLER_NAME}' should appear as running in Configuration > Pollers."
