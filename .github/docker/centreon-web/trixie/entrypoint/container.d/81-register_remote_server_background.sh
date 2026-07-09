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
REGISTER_EXIT=$?
rm -f "$TEMPLATE_FILE"
if [ $REGISTER_EXIT -ne 0 ]; then
  echo "registerServerTopology.sh failed (exit code: ${REGISTER_EXIT}) for '${REMOTE_SERVER_NAME}' -> '${CENTRAL_HOST}'"
  exit 1
fi

# Step 2: get auth token from central
# api/index.php uses centreon-auth-token (no PHP session needed), unlike internal.php
echo "Getting auth token from central ..."
AUTH_RESPONSE=$(curl -s -m 30 -X POST \
  -d "username=${CENTRAL_API_USERNAME}&password=${CENTRAL_API_PASSWORD}" \
  "http://${CENTRAL_HOST}/centreon/api/index.php?action=authenticate")
AUTH_TOKEN=$(echo "$AUTH_RESPONSE" | python3 -c "import sys, json; d=json.load(sys.stdin); print(d['authToken'])" 2>/dev/null)
if [ -z "$AUTH_TOKEN" ]; then
  echo "Failed to get auth token from central (response: ${AUTH_RESPONSE})"
  exit 1
fi

# Step 3: call linkCentreonRemoteServer via api/index.php (token-based auth, no session required)
echo "Linking remote server to central via wizard API ..."
LINK_PAYLOAD=$(jq -n \
  --arg server_name "$REMOTE_SERVER_NAME" \
  --arg central_ip "$CENTRAL_HOST" \
  --arg db_user "$DB_USER" \
  --arg db_password "$DB_PASSWORD" \
  '{
    manage_broker_configuration: "1",
    server_type: "remote",
    server_name: $server_name,
    server_ip: $server_name,
    centreon_central_ip: $central_ip,
    db_user: $db_user,
    db_password: $db_password,
    centreon_folder: "/centreon/",
    open_broker_flow: false,
    no_check_certificate: true,
    no_proxy: true,
    linked_pollers: [],
    linked_remote_master: "",
    linked_remote_slaves: []
  }')
LINK_RESPONSE=$(curl -s -m 60 \
  -H "centreon-auth-token: ${AUTH_TOKEN}" \
  -X POST -H "Content-Type: application/json" \
  -d "$LINK_PAYLOAD" \
  "http://${CENTRAL_HOST}/centreon/api/index.php?object=centreon_configuration_remote&action=linkCentreonRemoteServer")

echo "linkCentreonRemoteServer response: ${LINK_RESPONSE}"

TASK_ID=$(echo "$LINK_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('task_id',''))" 2>/dev/null)
SUCCESS=$(echo "$LINK_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(str(d.get('success','')).lower())" 2>/dev/null)

if [ "$SUCCESS" != "true" ]; then
  echo "linkCentreonRemoteServer failed: ${LINK_RESPONSE}"
  exit 1
fi

# Step 4: poll task status until completed
if [ -n "$TASK_ID" ] && [ "$TASK_ID" != "null" ]; then
  echo "Waiting for configuration export task ${TASK_ID} to complete ..."
  retries=0
  until [ $retries -ge $MAX_RETRIES ]; do
    sleep 2
    STATUS_RESPONSE=$(curl -s -m 10 \
      -H "centreon-auth-token: ${AUTH_TOKEN}" \
      -X POST -H "Content-Type: application/json" \
      -d "{\"task_id\": ${TASK_ID}}" \
      "http://${CENTRAL_HOST}/centreon/api/index.php?object=centreon_task_service&action=getTaskStatus")

    STATUS=$(echo "$STATUS_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('status',''))" 2>/dev/null)
    echo "Task status: ${STATUS}"
    if [ "$STATUS" = "completed" ]; then
      echo "Remote server '${REMOTE_SERVER_NAME}' successfully linked to central '${CENTRAL_HOST}'."
      break
    elif [ "$STATUS" = "failed" ] || [ "$STATUS" = "error" ]; then
      echo "Configuration export task failed (status: '${STATUS}'): ${STATUS_RESPONSE}"
      exit 1
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

# Step 5: get JWT token from central (for /api/latest/ endpoints)
echo "Getting JWT token from central ..."
JWT_RESPONSE=$(curl -s -m 30 -X POST \
  -H "Content-Type: application/json" \
  -d "{\"security\":{\"credentials\":{\"login\":\"${CENTRAL_API_USERNAME}\",\"password\":\"${CENTRAL_API_PASSWORD}\"}}}" \
  "http://${CENTRAL_HOST}/centreon/api/latest/login")
JWT_TOKEN=$(echo "$JWT_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['security']['token'])" 2>/dev/null)
if [ -z "$JWT_TOKEN" ]; then
  echo "Failed to get JWT token (response: ${JWT_RESPONSE})"
  exit 1
fi

# Step 6: get monitoring server IDs from central
echo "Getting monitoring server IDs ..."
SERVERS_RESPONSE=$(curl -s -m 30 \
  -H "X-AUTH-TOKEN: ${JWT_TOKEN}" \
  "http://${CENTRAL_HOST}/centreon/api/latest/configuration/monitoring-servers")
CENTRAL_SERVER_ID=$(echo "$SERVERS_RESPONSE" | python3 -c "
import sys, json
data = json.load(sys.stdin)
for s in data.get('result', []):
    if s.get('is_localhost'):
        print(s['id'])
        break
" 2>/dev/null)
REMOTE_SERVER_ID=$(echo "$SERVERS_RESPONSE" | python3 -c "
import sys, json, os
data = json.load(sys.stdin)
name = os.environ.get('REMOTE_SERVER_NAME', 'remote-server')
for s in data.get('result', []):
    if s.get('name') == name or s.get('address') == name:
        print(s['id'])
        break
" 2>/dev/null)
if [ -z "$CENTRAL_SERVER_ID" ] || [ -z "$REMOTE_SERVER_ID" ]; then
  echo "Failed to get server IDs (central: '${CENTRAL_SERVER_ID}', remote: '${REMOTE_SERVER_ID}')"
  echo "Servers response: ${SERVERS_RESPONSE}"
  exit 1
fi
echo "Central server ID: ${CENTRAL_SERVER_ID}, Remote server ID: ${REMOTE_SERVER_ID}"

# Step 7: get thumbprint from central Gorgone
# Gorgone may not be ready immediately after registerServerTopology.sh, so retry.
echo "Getting thumbprint from central Gorgone ..."
THUMBPRINT_TOKEN=""
retries=0
until [ $retries -ge $MAX_RETRIES ]; do
  THUMBPRINT_TOKEN_RESPONSE=$(curl -s -m 30 -X POST \
    -H "X-AUTH-TOKEN: ${JWT_TOKEN}" \
    "http://${CENTRAL_HOST}/centreon/api/latest/gorgone/pollers/${CENTRAL_SERVER_ID}/commands/thumbprint")
  THUMBPRINT_TOKEN=$(echo "$THUMBPRINT_TOKEN_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('token',''))" 2>/dev/null)
  if [ -n "$THUMBPRINT_TOKEN" ]; then
    break
  fi
  echo "Waiting for central Gorgone to be ready (response: ${THUMBPRINT_TOKEN_RESPONSE}) ..."
  sleep 5
  retries=$((retries + 1))
done
if [ -z "$THUMBPRINT_TOKEN" ]; then
  echo "Timeout waiting for central Gorgone to accept thumbprint command"
  exit 1
fi

THUMBPRINT=""
retries=0
until [ $retries -ge 30 ]; do
  sleep 2
  THUMBPRINT_RESPONSE=$(curl -s -m 10 \
    -H "X-AUTH-TOKEN: ${JWT_TOKEN}" \
    "http://${CENTRAL_HOST}/centreon/api/latest/gorgone/pollers/${CENTRAL_SERVER_ID}/responses/${THUMBPRINT_TOKEN}")
  THUMBPRINT=$(echo "$THUMBPRINT_RESPONSE" | python3 -c "
import sys, json
d = json.load(sys.stdin)
logs = d.get('data', [])
if logs:
    last = logs[-1]
    inner = json.loads(last.get('data', '{}'))
    tp = inner.get('data', {}).get('thumbprint', '')
    if tp:
        print(tp)
" 2>/dev/null)
  if [ -n "$THUMBPRINT" ]; then
    break
  fi
  retries=$((retries + 1))
done
if [ -z "$THUMBPRINT" ]; then
  echo "Timeout waiting for thumbprint from central Gorgone"
  exit 1
fi
echo "Got thumbprint: ${THUMBPRINT}"

# Step 8: write Gorgone ZMQ configuration on this remote server
echo "Writing Gorgone ZMQ configuration ..."
GORGONE_CONFIG="/etc/centreon-gorgone/config.d/40-gorgoned.yaml"
REMOTE_YAML_TEMPLATE="/usr/share/centreon/www/include/configuration/configServers/popup/remote.yaml"
export THUMBPRINT REMOTE_SERVER_ID REMOTE_SERVER_NAME
python3 - "$REMOTE_YAML_TEMPLATE" "$GORGONE_CONFIG" << 'PYEOF'
import sys, os

with open(sys.argv[1]) as f:
    config = f.read()

replacements = {
    '__SERVERNAME__': os.environ['REMOTE_SERVER_NAME'],
    '__SERVERID__': os.environ['REMOTE_SERVER_ID'],
    '__GORGONEPORT__': '5556',
    '__THUMBPRINT__': '\n      - key: ' + os.environ['THUMBPRINT'],
    '__COMMAND__': '/var/lib/centreon-engine/rw/centengine.cmd',
    '__CENTREON_VARLIB__': '/var/lib/centreon',
    '__CENTREON_CACHEDIR__': '/var/cache/centreon',
}
for placeholder, value in replacements.items():
    config = config.replace(placeholder, value)

with open(sys.argv[2], 'w') as f:
    f.write(config)
print(f'Gorgone config written to {sys.argv[2]}')
PYEOF

# Step 9: restart Gorgone on this remote server
echo "Restarting Gorgone ..."
systemctl restart gorgoned
echo "Gorgone restarted, waiting for ZMQ connection to central ..."
sleep 10

# Step 10: generate and reload configuration for this remote server from central
echo "Generating and reloading configuration for remote server (ID: ${REMOTE_SERVER_ID}) ..."
GEN_HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -m 120 \
  -H "X-AUTH-TOKEN: ${JWT_TOKEN}" \
  "http://${CENTRAL_HOST}/centreon/api/latest/configuration/monitoring-servers/${REMOTE_SERVER_ID}/generate-and-reload")
if [ "$GEN_HTTP_CODE" = "204" ]; then
  echo "Configuration generated and reloaded successfully."
else
  echo "Warning: generate-and-reload returned HTTP ${GEN_HTTP_CODE}"
fi

echo "Restarting cbd and centengine on remote server ..."
systemctl restart cbd centengine

# Step 11: generate and reload configuration for the central server
# linkCentreonRemoteServer updated the central's broker configuration in the DB;
# this applies it so that cbd and centengine on the central pick up the new remote server.
# Run after remote daemons are up so the central can immediately connect to the remote broker.
echo "Generating and reloading configuration for central server (ID: ${CENTRAL_SERVER_ID}) ..."
GEN_CENTRAL_HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -m 120 \
  -H "X-AUTH-TOKEN: ${JWT_TOKEN}" \
  "http://${CENTRAL_HOST}/centreon/api/latest/configuration/monitoring-servers/${CENTRAL_SERVER_ID}/generate-and-reload")
if [ "$GEN_CENTRAL_HTTP_CODE" = "204" ]; then
  echo "Central configuration generated and reloaded successfully."
else
  echo "Warning: generate-and-reload for central server returned HTTP ${GEN_CENTRAL_HTTP_CODE}"
fi

touch /tmp/remote-server.registered
echo "Remote server '${REMOTE_SERVER_NAME}' is now running."
