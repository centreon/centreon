#!/bin/bash

# Only run in remote server mode (requires CENTRAL_HOST to be set)
[ -z "${CENTRAL_HOST:-}" ] && exit 0

CENTRAL_HOST="${CENTRAL_HOST}"
CENTRAL_API_USERNAME="${CENTRAL_API_USERNAME:-admin}"
CENTRAL_API_PASSWORD="${CENTRAL_API_PASSWORD:-Centreon!2021}"
REMOTE_SERVER_NAME="${REMOTE_SERVER_NAME:-remote-server}"
DB_USER="${MYSQL_USER:-centreon}"
DB_PASSWORD="${MYSQL_PASSWORD:-centreon}"
MAX_RETRIES=120

wait_for_api() {
  local url="$1"
  local label="$2"
  local retries=0
  echo "Waiting for $label API at $url ..."
  until curl -sf "$url" > /dev/null; do
    sleep 5
    retries=$((retries + 1))
    if [ $retries -ge $MAX_RETRIES ]; then
      echo "Timeout waiting for $label"
      exit 1
    fi
  done
  echo "$label is ready."
}

wait_for_api "http://localhost/centreon/api/latest/platform/versions" "remote-server (local)"
wait_for_api "http://${CENTRAL_HOST}/centreon/api/latest/platform/versions" "central (${CENTRAL_HOST})"

# Step 1: convert this server to a remote and register it to the central
TEMPLATE_FILE=$(mktemp)
cat > "$TEMPLATE_FILE" <<EOF
API_USERNAME="${CENTRAL_API_USERNAME}"
API_TARGET_PASSWORD="${CENTRAL_API_PASSWORD}"
CURRENT_NODE_TYPE="remote"
TARGET_NODE_ADDRESS="${CENTRAL_HOST}"
CURRENT_NODE_NAME="${REMOTE_SERVER_NAME}"
CURRENT_NODE_ADDRESS="${REMOTE_SERVER_NAME}"
API_CURRENT_NODE_USERNAME="${CENTRAL_API_USERNAME}"
API_CURRENT_NODE_PASSWORD="${CENTRAL_API_PASSWORD}"
EOF

# Note: --insecure is passed on the command line only (not in the template) to avoid
# "duplicate flag" error in registerServerTopology.sh (template sourcing + CLI flag conflict)
echo "Registering remote server '${REMOTE_SERVER_NAME}' to central '${CENTRAL_HOST}' ..."
/usr/share/centreon/bin/registerServerTopology.sh --insecure --template "$TEMPLATE_FILE"
rm -f "$TEMPLATE_FILE"

# Step 2: call linkCentreonRemoteServer on the central to configure broker, DB and generate configs
# (equivalent to filling and submitting the wizard form from the Central UI)
echo "Getting auth token from central ..."
AUTH_RESPONSE=$(curl -sf -X POST -H "Content-Type: application/json" \
  -d "{\"security\":{\"credentials\":{\"login\":\"${CENTRAL_API_USERNAME}\",\"password\":\"${CENTRAL_API_PASSWORD}\"}}}" \
  "http://${CENTRAL_HOST}/centreon/api/latest/login")

AUTH_TOKEN=$(echo "$AUTH_RESPONSE" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
if [ -z "$AUTH_TOKEN" ]; then
  echo "Failed to get auth token from central"
  exit 1
fi

echo "Linking remote server to central via wizard API ..."
LINK_RESPONSE=$(curl -sf -X POST -H "Content-Type: application/json" \
  -H "centreon-auth-token: ${AUTH_TOKEN}" \
  -d "{
    \"manage_broker_configuration\": \"1\",
    \"server_type\": \"remote\",
    \"server_name\": \"${REMOTE_SERVER_NAME}\",
    \"server_ip\": \"${REMOTE_SERVER_NAME}\",
    \"centreon_central_ip\": \"${CENTRAL_HOST}\",
    \"db_user\": \"${DB_USER}\",
    \"db_password\": \"${DB_PASSWORD}\",
    \"centreon_folder\": \"/centreon/\",
    \"open_broker_flow\": false,
    \"no_check_certificate\": true,
    \"no_proxy\": true,
    \"linked_pollers\": [],
    \"linked_remote_master\": \"\",
    \"linked_remote_slaves\": []
  }" \
  "http://${CENTRAL_HOST}/centreon/api/internal.php?object=centreon_configuration_remote&action=linkCentreonRemoteServer")

TASK_ID=$(echo "$LINK_RESPONSE" | grep -o '"task_id":[^,}]*' | cut -d':' -f2 | tr -d ' "')
SUCCESS=$(echo "$LINK_RESPONSE" | grep -o '"success":[^,}]*' | cut -d':' -f2 | tr -d ' ')

if [ "$SUCCESS" != "true" ] && [ "$SUCCESS" != "1" ]; then
  echo "linkCentreonRemoteServer failed: $LINK_RESPONSE"
  exit 1
fi

# Step 3: poll task status until completed
if [ -n "$TASK_ID" ] && [ "$TASK_ID" != "null" ]; then
  echo "Waiting for configuration export task ${TASK_ID} to complete ..."
  retries=0
  until [ $retries -ge $MAX_RETRIES ]; do
    sleep 2
    STATUS_RESPONSE=$(curl -sf -X POST -H "Content-Type: application/json" \
      -H "centreon-auth-token: ${AUTH_TOKEN}" \
      -d "{\"task_id\": ${TASK_ID}}" \
      "http://${CENTRAL_HOST}/centreon/api/internal.php?object=centreon_task_service&action=getTaskStatus")

    STATUS=$(echo "$STATUS_RESPONSE" | grep -o '"status":"[^"]*' | cut -d'"' -f4)
    echo "Task status: ${STATUS}"
    if [ "$STATUS" = "completed" ]; then
      echo "Remote server '${REMOTE_SERVER_NAME}' successfully linked to central '${CENTRAL_HOST}'."
      break
    fi
    retries=$((retries + 1))
  done
  if [ $retries -ge $MAX_RETRIES ]; then
    echo "Timeout waiting for export task to complete"
    exit 1
  fi
else
  echo "Remote server '${REMOTE_SERVER_NAME}' successfully linked to central '${CENTRAL_HOST}'."
fi
