#!/bin/sh

# Configure Broker Graphite/InfluxDB outputs toward the "graphite"/"influxdb"
# profile receivers, through the configuration REST API (the UI path): it sets
# the event subscription (filters) and cache that the CLAPI path leaves unset.

BROKER_ID=1   # central-broker-master config id (always 1 in the test dataset)
API="http://${CENTREON_INTERNAL_API_BASE_URL:-127.0.0.1:80}/centreon/api/latest"
PASS='Centreon!2021'

api_token() {
  jq -nc --arg pass "${PASS}" '{security: {credentials: {login: "admin", password: $pass}}}' \
    | curl -s -d @- -L "${API}/login" | jq -r '.security.token // empty'
}

add_output() { # $1=token  $2=label  $3=json payload
  resp=$(mktemp)
  code=$(curl -s -o "${resp}" -w '%{http_code}' \
    -X POST -H "X-AUTH-TOKEN: $1" -H "Content-Type: application/json" \
    -L "${API}/configuration/broker/${BROKER_ID}/outputs" --data "$3")
  if [ "${code:-0}" -ge 200 ] && [ "${code:-0}" -lt 300 ]; then
    echo "75-broker-outputs: $2 output created (HTTP ${code})"
  else
    echo "75-broker-outputs: failed to create $2 output (HTTP ${code}): $(cat "${resp}" 2>/dev/null)" >&2
  fi
  rm -f "${resp}"
}

graphite=false
influxdb=false
[ -n "${GRAPHITE_HOST:-}" ] && timeout 0.2 getent ahostsv4 "${GRAPHITE_HOST}" >/dev/null && graphite=true
[ -n "${INFLUXDB_HOST:-}" ] && timeout 0.2 getent ahostsv4 "${INFLUXDB_HOST}" >/dev/null && influxdb=true

if [ "${graphite}" = true ] || [ "${influxdb}" = true ]; then
  TOKEN=$(api_token)
  if [ -z "${TOKEN}" ]; then
    echo "75-broker-outputs: authentication failed, skipping broker output configuration" >&2
  else
    if [ "${graphite}" = true ]; then
      add_output "${TOKEN}" graphite "$(jq -nc --arg host "${GRAPHITE_HOST}" '{
        name: "graphite-output", type: 30,
        parameters: {
          db_host: $host, db_port: 2003, db_user: "", db_password: "",
          queries_per_transaction: 0,
          metric_naming: "centreon.metric.$INSTANCE$.$HOST$.$SERVICE$.$SERV_TAG_CAT_NAME$.$METRIC$",
          status_naming: "centreon.status.$INSTANCE$.$HOST$.$SERVICE$.$SERV_TAG_CAT_NAME$",
          filters_category: ["neb", "storage"]
        }
      }')"
    fi
    if [ "${influxdb}" = true ]; then
      add_output "${TOKEN}" influxdb "$(jq -nc --arg host "${INFLUXDB_HOST}" --arg pass "${PASS}" '{
        name: "influxdb-output", type: 31,
        parameters: {
          cache: "no",
          db_host: $host, db_port: 8086, db_user: "centreon", db_password: $pass, db_name: "centreon",
          metrics_timeseries: "centreon.metrics.$INSTANCE$.$HOST$.$SERVICE$.$SERV_TAG_CAT_NAME$",
          status_timeseries: "centreon.status.$INSTANCE$.$HOST$.$SERVICE$.$SERV_TAG_CAT_NAME$",
          metrics_column: [], status_column: [],
          filters_category: ["neb", "storage"]
        }
      }')"
    fi
    # Reload cbd so it picks up the new outputs. centengine is left as-is: its
    # config is unchanged, and the container systemctl shim cannot restart it.
    sudo -u apache centreon -u admin -p "${PASS}" -a POLLERGENERATE -v 1
    sudo -u apache centreon -u admin -p "${PASS}" -a CFGMOVE -v 1
    systemctl restart cbd || echo "75-broker-outputs: cbd restart returned non-zero (check cbd logs)"
  fi
fi
:
