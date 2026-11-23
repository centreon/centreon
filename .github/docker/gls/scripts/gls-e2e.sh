#!/usr/bin/env bash
# Gorgone-less light Central PoC (MON-215451): stack driver and e2e harness.
#
#   gls-e2e.sh up                 gls-central (cbd generates its CA), new poller from env + CA of the creation answer, first export
#   gls-e2e.sh step0              no gorgone, upstream data in centreon_storage, poller outbound-only
#   gls-e2e.sh c1 central|poller  realtime actions (forced check, downtime, ack) in centengine.log
#   gls-e2e.sh c2 central|poller  export + reload applied (marker host, no error, after T0)
#   gls-e2e.sh c3 stop|partition|pause   explicit error <= 30 s on a disconnected poller, no replay
#   gls-e2e.sh plugins            packages listed in the poller plugins.json get installed
#   gls-e2e.sh tls-negative       central cert not signed by the CA: poller refuses, web answers 503/504; renewal keeps the CA
#   gls-e2e.sh ca                 self-generated CA: same as at creation, kept on cbd recreate, wrong fingerprint fails closed
#   gls-e2e.sh state              state volumes: layout, retention after recreate, cbmod queue while the central is down
#   gls-e2e.sh logs | down
#
# Env: GLS_REGISTRY, GLS_WEB_TAG, GLS_ENGINE_TAG, GLS_BROKER_TAG override images.env;
# LOG_DIR (default /tmp/gls-e2e), SUMMARY_FILE (markdown summary, default none),
# GLS_CERTS_DIR (rogue PKI of the negative TLS test, default <gls>/.certs).
set -uo pipefail

# Git Bash (MSYS) on Windows rewrites arguments that look like POSIX paths
# ("/CN=..." for openssl, container paths for docker exec). Turn it off, and
# hand Windows binaries (openssl, docker) mixed C:/... paths for host files.
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL="*"
host_path() { if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else printf '%s' "$1"; fi; }

GLS_DIR=$(host_path "$(cd "$(dirname "$0")/.." && pwd)")
set -a
# shellcheck disable=SC1091
. "$GLS_DIR/images.env"
REGISTRY="${GLS_REGISTRY:-$REGISTRY}"
WEB_TAG="${GLS_WEB_TAG:-$WEB_TAG}"
ENGINE_TAG="${GLS_ENGINE_TAG:-$ENGINE_TAG}"
BROKER_TAG="${GLS_BROKER_TAG:-$BROKER_TAG}"
HTTPD_PORT="${HTTPD_PORT:-18180}"
POLLER_NAME="${POLLER_NAME:-gls-poller}"
# Run-time throwaway PKI, mounted by both compose files (gitignored).
GLS_CERTS_DIR=$(host_path "${GLS_CERTS_DIR:-$GLS_DIR/.certs}")
set +a

# Host path for every native binary (curl -o, jq, openssl, docker -f/-v): on
# Git Bash /tmp is converted to C:/.../Temp, elsewhere unchanged.
LOG_DIR=$(host_path "${LOG_DIR:-${TMPDIR:-/tmp}/gls-e2e}")
SUMMARY_FILE="${SUMMARY_FILE:-/dev/null}"
ADMIN_PW="${ADMIN_PW:-Centreon!2021}"
API="http://127.0.0.1:${HTTPD_PORT}/centreon/api/latest"
MAX_ERROR_DELAY="${MAX_ERROR_DELAY:-30}"
mkdir -p "$LOG_DIR"

cc() { docker compose -p gls-central -f "$GLS_DIR/docker-compose.central.yml" "$@"; }
cp_() { docker compose -p gls-poller -f "$GLS_DIR/docker-compose.poller.yml" "$@"; }

results=()
failures=0
record() { # record <check> ok|ko [detail]
  local mark=":white_check_mark:"
  if [[ "$2" != ok ]]; then mark=":x:"; failures=$((failures + 1)); echo "::error::gls: $1 ${3:-}"; fi
  results+=("| $1 | $mark | ${3:-} |")
  echo "[$2] $1 ${3:-}"
}
summary() {
  {
    echo "### gls e2e: $*"
    echo
    echo "| Check | Result | Detail |"
    echo "| --- | --- | --- |"
    printf '%s\n' "${results[@]}"
    echo
  } >>"$SUMMARY_FILE"
}
now() { date -u +%Y-%m-%dT%H:%M:%SZ; }

# ---------- helpers ----------

sql() { # sql <db> <query>: tab-separated, no header
  cc exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb --skip-ssl -uroot -N -B "$0" -e "$1"' "$1" "$2"
}

clapi() { cc exec -T -u www-data fpm /usr/share/centreon/bin/centreon -u admin -p "$ADMIN_PW" "$@"; }

TOKEN=""
login() {
  TOKEN=$(curl -s -H 'Content-Type: application/json' \
    -d "{\"security\":{\"credentials\":{\"login\":\"admin\",\"password\":\"${ADMIN_PW}\"}}}" \
    "$API/login" | jq -r '.security.token // empty')
  [[ -n "$TOKEN" ]]
}

api() { # api <METHOD> <path> [json] -> prints HTTP code, body in $LOG_DIR/last.json
  local method=$1 path=$2 body=${3:-}
  local args=(-s -o "$LOG_DIR/last.json" -w '%{http_code}' -X "$method" -H "X-AUTH-TOKEN: $TOKEN" --max-time "${API_MAX_TIME:-180}")
  [[ -n "$body" ]] && args+=(-H 'Content-Type: application/json' -d "$body")
  # Exactly one code on stdout: curl's, or 000 when it got none. A curl error
  # after a code (e.g. rc 23, body not written) is logged, not appended.
  local code rc
  code=$(curl "${args[@]}" "$API$path"); rc=$?
  if (( rc != 0 )); then
    echo "$(date -u +%FT%TZ) curl rc=$rc code=${code:-none} $method $path" >>"$LOG_DIR/curl-errors.log"
    [[ -f "$LOG_DIR/last.json" ]] || : >"$LOG_DIR/last.json"
  fi
  [[ "$code" =~ ^[0-9]{3}$ ]] || code=000
  printf '%s' "$code"
}

service_ready() { # service_ready <compose fn> <service>
  local id state health
  id=$("$1" ps -a -q "$2" 2>/dev/null | head -1)
  [[ -n "$id" ]] || return 1
  state=$(docker inspect -f '{{.State.Status}}' "$id" 2>/dev/null)
  health=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$id" 2>/dev/null)
  [[ "$state" == running ]] && { [[ -z "$health" ]] || [[ "$health" == healthy ]]; }
}

wait_until() { # wait_until <timeout s> <cmd...>
  local deadline=$(( $(date +%s) + $1 )); shift
  until "$@"; do (( $(date +%s) >= deadline )) && return 1; sleep 5; done
}

poller_id() { sql centreon "SELECT id FROM nagios_server WHERE name='${POLLER_NAME}'" | head -1; }

target_container() { # central|poller -> "<compose fn> <service>"
  if [[ "$1" == central ]]; then echo "cc central-engine"; else echo "cp_ poller-engine"; fi
}
target_host() { if [[ "$1" == central ]]; then echo gls-hc; else echo gls-hp; fi; }
target_poller_id() { if [[ "$1" == central ]]; then echo 1; else poller_id; fi; }

engine_logs() { # engine_logs <central|poller> <since> [-t]
  local fn svc
  read -r fn svc <<<"$(target_container "$1")"
  "$fn" logs --no-log-prefix --no-color ${3:-} --since "$2" "$svc" 2>/dev/null
}

wait_log() { # wait_log <target> <since> <fixed string> <timeout>
  local deadline=$(( $(date +%s) + $4 ))
  while (( $(date +%s) < deadline )); do
    engine_logs "$1" "$2" | grep -qF -- "$3" && return 0
    sleep 2
  done
  return 1
}

# ---------- up / bootstrap ----------

CLAPI_LOG="$LOG_DIR/clapi.log"
clapi_log() { echo "+ clapi $*" >>"$CLAPI_LOG"; clapi "$@" >>"$CLAPI_LOG" 2>&1; }

add_host() { # add_host <name> <instance>
  clapi_log -o HOST -a ADD -v "$1;$1;127.0.0.1;;$2;"
  local p
  for p in "max_check_attempts;1" "check_period;24x7" "check_interval;1" "retry_check_interval;1" \
           "active_checks_enabled;1" "passive_checks_enabled;1" "check_command;gls_check_dummy" \
           "check_command_arguments;!0!up"; do
    clapi_log -o HOST -a setparam -v "$1;$p"
  done
}

add_service_template() { # CLAPI SERVICE ADD needs a template; STPL ADD does not.
  clapi_log -o STPL -a ADD -v "gls-stpl;gls-stpl;"
  local p
  for p in "max_check_attempts;1" "check_period;24x7" "normal_check_interval;1" "retry_check_interval;1" \
           "active_checks_enabled;1" "passive_checks_enabled;1" "check_command;gls_check_dummy"; do
    clapi_log -o STPL -a setparam -v "gls-stpl;$p"
  done
}

add_service() { # add_service <host>
  clapi_log -o SERVICE -a ADD -v "$1;gls-crit;gls-stpl"
  clapi_log -o SERVICE -a setparam -v "$1;gls-crit;check_command_arguments;!2!gls critical"
  [[ -n "$(sql centreon "SELECT s.service_id FROM service s JOIN host_service_relation r ON r.service_service_id=s.service_id JOIN host h ON h.host_id=r.host_host_id WHERE h.host_name='$1' AND s.service_description='gls-crit'")" ]]
}

# Central poller bootstrap only (contracts § 6.S): its first config is the
# web's local copy, made while it is still on the gorgone channel (cbd is not
# running yet), so its cbmod lacks remote_actions. Never _insecure.
patch_keys() { # patch_keys api|actions <glob evaluated inside fpm>
  cc exec -T -u www-data fpm sh -c "php -- $1 $2" <"$(dirname "$0")/patch-remote-keys.php" >>"$LOG_DIR/patch-keys.log" 2>&1
}

# Negative TLS test only: a throwaway rogue CA + server cert with the
# central's name (CN/SAN centreon-broker), generated at run time, never
# committed. The real PKI is generated by cbd itself (contracts § 8.1).
make_rogue() {
  local d="$GLS_CERTS_DIR"
  [[ -s "$d/rogue.crt" && -s "$d/rogue.key" ]] && return 0
  rm -rf "$d"; mkdir -p "$d" "$LOG_DIR"; : >"$LOG_DIR/certs.log"
  # Self-contained openssl config: independent of the host defaults.
  printf '%s\n' '[req]' 'distinguished_name = dn' 'prompt = no' '[dn]' 'CN = unused' \
    '[ca_ext]' 'basicConstraints = critical,CA:TRUE' 'keyUsage = critical,keyCertSign,cRLSign' \
    '[srv_ext]' 'subjectAltName = DNS:centreon-broker' 'extendedKeyUsage = serverAuth' >"$d/openssl.cnf"
  if openssl req -config "$d/openssl.cnf" -extensions ca_ext -x509 -newkey rsa:2048 -nodes -days 1 \
       -subj "/CN=gls-poc-rogue-ca" -keyout "$d/rogue-ca.key" -out "$d/rogue-ca.crt" 2>>"$LOG_DIR/certs.log" &&
     openssl req -config "$d/openssl.cnf" -newkey rsa:2048 -nodes -subj "/CN=centreon-broker" \
       -keyout "$d/rogue.key" -out "$d/rogue.csr" 2>>"$LOG_DIR/certs.log" &&
     openssl x509 -req -in "$d/rogue.csr" -CA "$d/rogue-ca.crt" -CAkey "$d/rogue-ca.key" -CAcreateserial -days 1 \
       -extfile "$d/openssl.cnf" -extensions srv_ext -out "$d/rogue.crt" 2>>"$LOG_DIR/certs.log" &&
     [[ -s "$d/rogue.crt" && -s "$d/rogue.key" ]]; then
    rm -f "$d"/*.csr "$d"/*.srl "$d/openssl.cnf"
    chmod 0644 "$d"/*.crt; chmod 0600 "$d"/*.key
    return 0
  fi
  echo "::error::gls: rogue PKI generation failed in $d, openssl output:" >&2
  cat "$LOG_DIR/certs.log" >&2
  rm -rf "$d"; return 1
}

cbd_get() { # cbd_get <path>: GET on the central cbd remote API, through fpm (internal network)
  cc exec -T fpm php -d allow_url_fopen=On -r '
    $c = stream_context_create(["http" => ["header" => "Authorization: Bearer " . getenv("CENTREON_BROKER_API_TOKEN"), "timeout" => 5]]);
    echo @file_get_contents(getenv("CENTREON_BROKER_API_URL") . $argv[1], false, $c);' "$1"
}
cbd_ca_fp() { cbd_get /v1/ca | jq -r '.fingerprint_sha256 // empty' 2>/dev/null; }
cbd_ca_ready() { [[ -n "$(cbd_ca_fp)" ]]; }

cbd_pollers() { cbd_get /v1/pollers; }
poller_capable() { cbd_pollers | jq -e --arg u "$1" '.pollers[] | select(.poller_id == $u and .remote_actions == true)' >/dev/null 2>&1; }
poller_config_version() { cbd_pollers | jq -r --arg u "$1" '.pollers[] | select(.poller_id == $u) | .config_version // ""' 2>/dev/null; }

# Legacy export chain (what the UI export page calls), driven over HTTP with
# the REST token: generate, optional hook on the generated files, move, reload.
legacy_xml() { # legacy_xml <script> <body> -> 0 when statuscode is 0
  local out="$LOG_DIR/legacy-${1%.php}.xml"
  curl -s -o "$out" --max-time 180 -H "X-AUTH-TOKEN: $TOKEN" -d "$2" \
    "http://127.0.0.1:${HTTPD_PORT}/centreon/include/configuration/configGenerate/xml/$1" || return 1
  grep -qF -e "<statuscode><![CDATA[0]]></statuscode>" -e "<statuscode>0</statuscode>" "$out"
}

use_broker_channel() { # use_broker_channel <SQL condition> (contracts § 4)
  sql centreon "UPDATE nagios_server SET remote_channel='broker' WHERE $1"
}

cmd_up() {
  docker network inspect gls-wan >/dev/null 2>&1 || docker network create gls-wan >/dev/null
  cc pull --quiet && cp_ pull --quiet || { record "pull images" ko; return 1; }
  cc up -d || true
  if wait_until 1200 service_ready cc httpd; then record "central web up" ok; else record "central web up" ko; return 1; fi
  login || { record "REST login" ko; return 1; }

  # Step 0: the central poller is installed as poller_type=docker (gorgone
  # path). vm = the web's local copy into the central config volumes.
  sql centreon "UPDATE nagios_server SET poller_type='vm' WHERE localhost='1'"

  clapi -o CMD -a ADD -v 'gls_check_dummy;check;$USER1$/check_dummy $ARG1$ "$ARG2$"' >/dev/null 2>&1 || true
  add_service_template
  add_host gls-hc Central
  add_service gls-hc && record "fixture gls-hc/gls-crit" ok || record "fixture gls-hc/gls-crit" ko "see clapi.log"

  local code
  code=$(api GET /configuration/monitoring-servers/1/generate-and-reload)
  [[ "$code" == 204 ]] && record "central generate + local copy" ok "HTTP $code" \
    || record "central generate + local copy" ko "HTTP $code $(head -c 300 "$LOG_DIR/last.json")"
  patch_keys actions "/etc/centreon-broker/*-module.json" && record "central cbmod remote_actions (bootstrap only)" ok \
    || record "central cbmod remote_actions (bootstrap only)" ko "see patch-keys.log"
  wait_until 600 service_ready cc centreon-broker && record "centreon-broker running" ok || record "centreon-broker running" ko
  # cbd generates its CA + server cert at start (contracts § 8.1). The central
  # cbmod needs ca.crt and the patched keys: restart it once both exist.
  local ca_fp
  if wait_until 120 cbd_ca_ready; then
    ca_fp=$(cbd_ca_fp); record "central cbd generated its CA (GET /v1/ca)" ok "sha256 ${ca_fp:0:16}..."
  else
    record "central cbd generated its CA (GET /v1/ca)" ko "$(cbd_get /v1/ca | head -c 200)"; return 1
  fi
  echo "$ca_fp" >"$LOG_DIR/broker-ca.fp"
  cc restart central-engine >/dev/null 2>&1
  local cuid
  cuid=$(sql centreon "SELECT uid FROM nagios_server WHERE localhost='1'")
  wait_until 300 poller_capable "$cuid" && record "central engine connected over TLS, capable" ok || record "central engine connected over TLS, capable" ko
  # The central's first delivery had to be the local copy (cbd not running before it).
  use_broker_channel "localhost='1'"

  # New poller, created once cbd is up: id, uid and the central CA (trust
  # anchor, out of band of the stream) from the API answer (contracts § 8.4).
  local pid uid ca_pem
  pid=$(poller_id)
  if [[ -z "$pid" ]]; then
    api POST /administration/tokens '{"name":"gls-poller-token","type":"poller","expiration_date":null}' >/dev/null
    code=$(api POST /configuration/pollers "{\"name\":\"${POLLER_NAME}\",\"poller_type\":\"vm\",\"address\":\"poller-engine\",\"poller_token_name\":\"gls-poller-token\",\"central_address\":\"centreon-broker\"}")
    cp "$LOG_DIR/last.json" "$LOG_DIR/create-poller.json"
    pid=$(jq -r '.id // empty' "$LOG_DIR/create-poller.json"); uid=$(jq -r '.uid // empty' "$LOG_DIR/create-poller.json")
    [[ -n "$pid" && -n "$uid" ]] && record "create poller ${POLLER_NAME} (API)" ok "HTTP $code id=$pid uid=$uid" \
      || { record "create poller (API id + uid)" ko "HTTP $code $(head -c 200 "$LOG_DIR/create-poller.json")"; return 1; }
    ca_pem=$(jq -r '.broker_ca.pem // empty' "$LOG_DIR/create-poller.json")
    if [[ "$ca_pem" == *"BEGIN CERTIFICATE"* && "$(jq -r '.broker_ca.fingerprint_sha256 // empty' "$LOG_DIR/create-poller.json")" == "$ca_fp" ]]; then
      record "creation answer carries the central CA (broker_ca = GET /v1/ca)" ok
    else
      record "creation answer carries the central CA (broker_ca = GET /v1/ca)" ko "$(jq -c '{broker_ca, broker_ca_warning}' "$LOG_DIR/create-poller.json" 2>/dev/null | head -c 200)"
      return 1
    fi
  else
    # Rerun on an existing poller: no creation answer, same CA from cbd.
    uid=$(sql centreon "SELECT uid FROM nagios_server WHERE id=$pid")
    ca_pem=$(cbd_get /v1/ca | jq -r '.pem // empty')
  fi
  use_broker_channel "id=$pid"
  printf 'POLLER_ID=%s\nPOLLER_UID=%s\n' "$pid" "$uid" >"$LOG_DIR/poller.env"
  add_host gls-hp "${POLLER_NAME}"
  add_service gls-hp && record "fixture gls-hp/gls-crit" ok || record "fixture gls-hp/gls-crit" ko "see clapi.log"

  # New poller from env only (contracts § 6.3): no seed, no config volume content.
  # Env only: identity + the CA from the creation answer, no file mounted.
  POLLER_ID=$pid POLLER_UID=$uid BROKER_CA_PEM=$ca_pem BROKER_CA_FINGERPRINT=$ca_fp cp_ up -d || true
  if wait_until 600 poller_capable "$uid"; then
    record "new poller connected over TLS from its bootstrap, capable" ok "uid $uid"
  else
    record "new poller connected over TLS from its bootstrap, capable" ko "$(cbd_pollers | head -c 300)"; return 1
  fi
  [[ -z "$(poller_config_version "$uid")" ]] && record "new poller has no config version before the first export" ok \
    || record "new poller has no config version before the first export" ko

  # First config through the channel: an explicit export (restart: cbmod config changes).
  code=$(api GET "/configuration/monitoring-servers/$pid/generate-and-reload")
  [[ "$code" == 204 ]] && record "first export to the new poller" ok "HTTP $code" \
    || record "first export to the new poller" ko "HTTP $code $(head -c 300 "$LOG_DIR/last.json")"
  wait_until 300 eval '[[ -n "$(poller_config_version "$uid")" ]]' \
    && record "new poller reconnected with a config version" ok "$(poller_config_version "$uid" | cut -c1-12)" \
    || record "new poller reconnected with a config version" ko
  cp_ exec -T poller-engine grep -q 'host_name *gls-hp' /etc/centreon-engine/hosts.cfg \
    && record "delivered config in place on the poller (gls-hp)" ok || record "delivered config in place on the poller (gls-hp)" ko
  # The deploy must not touch certs/: the bootstrap CA is still there, same one.
  local pfp
  pfp=$(cp_ exec -T poller-engine cat /etc/centreon-broker/certs/ca.crt 2>/dev/null \
    | openssl x509 -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//; s/://g' | tr 'A-F' 'a-f')
  [[ -n "$pfp" && "$pfp" == "$ca_fp" ]] && record "poller CA kept after the first export (= creation answer)" ok \
    || record "poller CA kept after the first export (= creation answer)" ko "${pfp:-no ca.crt} vs ${ca_fp:0:16}"
  wait_until 300 service_ready cp_ poller-engine && record "poller connected to centreon-broker:5669" ok \
    || record "poller connected to centreon-broker:5669" ko
}

# ---------- negative TLS: central cert not signed by the pollers' CA ----------

swap_central_cert() { # swap_central_cert rogue|clear (clear: cbd re-signs with its kept CA)
  docker run --rm --network none -u 0 -v gls-central_central-broker-certs:/mnt/broker-certs \
    -v "$GLS_CERTS_DIR:/certs-src:ro" -v "$GLS_DIR/scripts:/gls:ro" --entrypoint /bin/sh \
    "$REGISTRY/centreon-engine-trixie:$ENGINE_TAG" /gls/install-certs.sh "$1" \
    >>"$LOG_DIR/install-certs.log" 2>&1 && cc restart centreon-broker >/dev/null 2>&1
}

cmd_tls_negative() {
  local host=gls-hp hid sid res s el code uid fp0
  uid=$(sql centreon "SELECT uid FROM nagios_server WHERE name='${POLLER_NAME}'")
  login || { record "login" ko; return; }
  read -r hid sid <<<"$(ids "$host")"
  res="[{\"type\":\"service\",\"id\":$sid,\"parent\":{\"id\":$hid}}]"
  fp0=$(cbd_ca_fp)
  make_rogue && record "rogue CA + central-named cert generated (test only)" ok || { record "rogue PKI" ko; return; }
  swap_central_cert rogue && record "central cbd restarted with a rogue cert" ok || { record "rogue cert installed" ko; return; }
  sleep 15
  poller_capable "$uid" && record "poller refuses the rogue central (no capable stream)" ko "$(cbd_pollers | head -c 200)" \
    || record "poller refuses the rogue central (no capable stream)" ok
  s=$(date +%s)
  code=$(API_MAX_TIME=$((MAX_ERROR_DELAY + 15)) api POST /monitoring/resources/check "{\"check\":{\"is_forced\":true},\"resources\":$res}")
  el=$(( $(date +%s) - s ))
  cp "$LOG_DIR/last.json" "$LOG_DIR/tls-negative-check.json"
  if [[ "$code" == 503 || "$code" == 504 ]] && grep -qF "$POLLER_NAME" "$LOG_DIR/last.json" && (( el <= MAX_ERROR_DELAY )); then
    record "realtime on the poller rejected explicitly" ok "HTTP $code in ${el} s"
  else
    record "realtime on the poller rejected explicitly <= ${MAX_ERROR_DELAY} s" ko "HTTP $code in ${el} s: $(head -c 150 "$LOG_DIR/last.json")"
  fi
  # Restore = renewal: server pair removed, cbd signs a new one with the same CA.
  swap_central_cert clear && record "central server pair removed, cbd restarted" ok || record "central server pair removed" ko
  wait_until 300 poller_capable "$uid" && record "poller reconnected over TLS after renewal" ok || record "poller reconnected over TLS after renewal" ko
  [[ -n "$fp0" && "$(cbd_ca_fp)" == "$fp0" ]] && record "renewal kept the CA" ok "sha256 ${fp0:0:16}..." \
    || record "renewal kept the CA" ko "${fp0:0:16} -> $(cbd_ca_fp | cut -c1-16)"
}

# ---------- self-generated CA (contracts § 8) ----------

cmd_ca() {
  local uid fp0 fp1 pem out rc
  uid=$(sql centreon "SELECT uid FROM nagios_server WHERE name='${POLLER_NAME}'")
  fp0=$(cat "$LOG_DIR/broker-ca.fp" 2>/dev/null)
  [[ -n "$fp0" && "$(cbd_ca_fp)" == "$fp0" ]] && record "GET /v1/ca = CA handed out at creation" ok "sha256 ${fp0:0:16}..." \
    || record "GET /v1/ca = CA handed out at creation" ko "${fp0:-none} vs $(cbd_ca_fp)"
  cc exec -T centreon-broker test -s /etc/centreon-broker/certs/ca.key >/dev/null 2>&1 \
    && ! cbd_get /v1/ca | grep -q "PRIVATE KEY" && record "CA key kept in the cbd cert volume, never served" ok \
    || record "CA key kept in the cbd cert volume, never served" ko
  # The CA survives a cbd container recreate (it lives in central-broker-certs).
  cc up -d --force-recreate --no-deps centreon-broker >>"$LOG_DIR/ca.log" 2>&1
  wait_until 300 poller_capable "$uid" && record "poller reconnected after a cbd recreate" ok || record "poller reconnected after a cbd recreate" ko
  fp1=$(cbd_ca_fp)
  [[ -n "$fp1" && "$fp1" == "$fp0" ]] && record "cbd recreate kept the CA" ok || record "cbd recreate kept the CA" ko "${fp0:0:16} -> ${fp1:0:16}"
  # Bootstrap with the CA from env, in a throwaway container laid out like the
  # poller (empty config, certs/ dir): right fingerprint renders a config
  # (control), wrong fingerprint exits before (fail closed).
  pem=$(cbd_get /v1/ca | jq -r '.pem // empty')
  # Internal network: no way out, but local name resolution (sudo, apt fail fast).
  docker network create --internal gls-ca-boot >/dev/null 2>&1
  local -a boot=(--network gls-ca-boot
    --tmpfs /etc/centreon-engine:mode=0777 --tmpfs /etc/centreon-broker:mode=0777
    --tmpfs /etc/centreon-broker/certs:mode=0777 --tmpfs /var/lib/centreon-engine:uid=901,gid=901,mode=0770
    --tmpfs /var/lib/centreon-engine/rw:uid=901,gid=901,mode=0770
    -e POLLER_NAME=gls-boot -e POLLER_ID=999 -e POLLER_UID=gls-boot -e CENTRAL_BROKER_HOST=centreon-broker
    -e BROKER_CA_PEM="$pem")
  docker rm -f gls-ca-boot >/dev/null 2>&1
  docker run -d --name gls-ca-boot "${boot[@]}" -e BROKER_CA_FINGERPRINT="$fp1" "$REGISTRY/centreon-engine-trixie:$ENGINE_TAG" >/dev/null 2>&1
  # Past the bootstrap = the engine reads the rendered config (the container may
  # stop later on its own: no central reachable, judged from the logs only).
  if wait_until 240 eval 'docker logs gls-ca-boot 2>&1 | grep -qF "Processing object config file" \
       && ! docker logs gls-ca-boot 2>&1 | grep -qi "fingerprint mismatch"'; then
    record "bootstrap accepts the CA with the right fingerprint (control)" ok
  else
    record "bootstrap accepts the CA with the right fingerprint (control)" ko "$(docker logs gls-ca-boot 2>&1 | tail -3 | tr '\n' ' ' | head -c 200)"
  fi
  docker logs gls-ca-boot >"$LOG_DIR/ca-boot-ok.log" 2>&1
  docker rm -f gls-ca-boot >/dev/null 2>&1
  out=$(timeout 300 docker run --name gls-ca-boot "${boot[@]}" -e BROKER_CA_FINGERPRINT="$(printf '0%.0s' {1..64})" \
    "$REGISTRY/centreon-engine-trixie:$ENGINE_TAG" 2>&1); rc=$?
  docker rm -f gls-ca-boot >/dev/null 2>&1
  echo "$out" >"$LOG_DIR/ca-boot-failclosed.log"
  docker network rm gls-ca-boot >/dev/null 2>&1
  [[ "$rc" != 0 && "$rc" != 124 ]] && grep -qi fingerprint <<<"$out" \
    && record "bootstrap refuses a CA with a wrong fingerprint (fail closed)" ok "exit $rc" \
    || record "bootstrap refuses a CA with a wrong fingerprint (fail closed)" ko "exit $rc: $(tail -3 <<<"$out" | tr '\n' ' ' | head -c 200)"
}

# The cbmod JSON the poller engine actually loads (the image also seeds the
# package-default central-module.json in that volume).
poller_module_json() {
  cp_ exec -T poller-engine sh -c 'f=$(grep -oE "/etc/centreon-broker/[^ \",]+\.json" /etc/centreon-engine/centengine.cfg | head -1); cat "$f"' 2>/dev/null
}

service_ready_running() { local id; id=$("$1" ps -q "$2" | head -1); [[ -n "$id" ]] && [[ "$(docker inspect -f '{{.State.Status}}' "$id")" == running ]]; }

# ---------- step 0 ----------

instance_running() { [[ "$(sql centreon_storage "SELECT COUNT(*) FROM instances WHERE name='$1' AND running=1 AND deleted=0")" -ge 1 ]]; }
service_critical() {
  [[ "$(sql centreon_storage "SELECT COUNT(*) FROM services s JOIN hosts h ON h.host_id=s.host_id WHERE h.name='$1' AND s.description='gls-crit' AND s.state=2 AND s.last_check>0")" -ge 1 ]]
}

outbound_only() { # every non-loopback LISTEN is a failure, every established socket on a listening port too
  cp_ exec -T poller-engine cat /proc/net/tcp /proc/net/tcp6 >"$LOG_DIR/poller-proc-net-tcp.txt" 2>/dev/null || return 1
  awk '
    # 127.0.0.0/8 (incl. Docker DNS 127.0.0.11), ::1, ::ffff:127.x (little-endian hex)
    function loop(a) { return a ~ /^[0-9A-F]{6}7F$/ || a == "00000000000000000000000001000000" || a ~ /^0000000000000000FFFF0000[0-9A-F]{6}7F$/ }
    $1 ~ /^[0-9]+:$/ {
      split($2, l, ":"); split($3, r, ":"); st = $4
      if (st == "0A") { lp[l[2]] = 1; if (!loop(l[1])) { print "listen on non-loopback: " $2; bad++ } }
      else if (st == "01") { est[NR] = l[2] " " r[1] " " r[2] }
    }
    END {
      for (i in est) { split(est[i], e, " "); if (e[1] in lp && !loop(e[2])) { print "inbound: " est[i]; bad++ } if (e[3] == "1625") out++ }
      printf "outbound_5669=%d bad=%d\n", out, bad
      exit (bad > 0 || out < 1)
    }' "$LOG_DIR/poller-proc-net-tcp.txt"
}

cmd_step0() {
  local n
  n=$( { cc ps -a --format '{{.Image}}'; cp_ ps -a --format '{{.Image}}'; } | grep -cE '/centreon-gorgone[^/]*:')
  [[ "$n" == 0 ]] && record "no gorgone container" ok || record "no gorgone container" ko "$n"
  cp_ ps -a --format '{{.Service}}' | grep -qx volumes-init \
    && record "no helper container in gls-poller (volumes seeded by the image)" ko \
    || record "no helper container in gls-poller (volumes seeded by the image)" ok
  local ports
  ports=$(cp_ ps --format '{{.Ports}}' | tr -d '[:space:]')
  [[ "$ports" != *"->"* ]] && record "poller publishes no port" ok || record "poller publishes no port" ko "$ports"
  docker ps --format '{{.Ports}}' | grep -q ':5669->' && record "no host port 5669" ko || record "no host port 5669" ok
  wait_until 300 instance_running Central && record "central engine instance running (centreon_storage.instances)" ok \
    || record "central engine instance running" ko
  wait_until 300 instance_running "$POLLER_NAME" && record "poller instance running (centreon_storage.instances)" ok \
    || record "poller instance running" ko
  wait_until 420 service_critical gls-hc && record "central check result upstream (gls-hc/gls-crit CRITICAL)" ok \
    || record "central check result upstream" ko
  wait_until 420 service_critical gls-hp && record "poller check result upstream (gls-hp/gls-crit CRITICAL)" ok \
    || record "poller check result upstream" ko
  local o
  o=$(outbound_only) && record "poller has only outbound sockets" ok "$o" || record "poller has only outbound sockets" ko "$(echo "$o" | tr '\n' ' ')"
  # Delivered cbmod config (not the bootstrap): gRPC TLS with the CA, remote actions, no escape hatch.
  local mod
  mod=$(poller_module_json)
  if jq -e '.centreonBroker as $b | ($b.remote_actions == "yes") and (($b.remote_actions_insecure // "no") != "yes")
        and ([$b.output[]? | select(.type == "bbdo_client" and .encryption == "yes" and (.ca_certificate // "") != "")] | length > 0)' <<<"$mod" >/dev/null 2>&1; then
    record "poller cbmod: gRPC TLS + CA, remote_actions, no insecure" ok
  else
    record "poller cbmod: gRPC TLS + CA, remote_actions, no insecure" ko "$(head -c 300 <<<"$mod")"
  fi
}

# ---------- criterion 1: realtime ----------

ids() { # ids <host> -> "host_id service_id"
  sql centreon_storage "SELECT s.host_id, s.service_id FROM services s JOIN hosts h ON h.host_id=s.host_id WHERE h.name='$1' AND s.description='gls-crit'" | head -1
}

realtime() { # realtime <target> <label> <api path> <json> <log pattern>
  local t0 code start end
  t0=$(now); start=$(date +%s)
  code=$(api POST "$3" "$4")
  end=$(date +%s)
  if [[ "$code" != 2* ]]; then
    record "$1: $2 API" ko "HTTP $code $(head -c 200 "$LOG_DIR/last.json")"; return
  fi
  if wait_log "$1" "$t0" "$5" 30; then
    record "$1: $2 in centengine.log" ok "HTTP $code in $((end - start)) s, log <= 30 s"
  else
    record "$1: $2 in centengine.log" ko "HTTP $code, no '$5' in 30 s"
  fi
}

cmd_c1() {
  local target=${1:?central|poller} host hid sid res
  host=$(target_host "$target")
  login || { record "login" ko; return; }
  read -r hid sid <<<"$(ids "$host")"
  [[ -n "${sid:-}" ]] || { record "$target: resource ids" ko; return; }
  res="[{\"type\":\"service\",\"id\":$sid,\"parent\":{\"id\":$hid}}]"
  realtime "$target" "forced check" /monitoring/resources/check \
    "{\"check\":{\"is_forced\":true},\"resources\":$res}" "EXTERNAL COMMAND: SCHEDULE_FORCED_SVC_CHECK;$host;gls-crit;"
  local s e
  s=$(date -u +%Y-%m-%dT%H:%M:%S+00:00); e=$(date -u -d '+1 hour' +%Y-%m-%dT%H:%M:%S+00:00)
  realtime "$target" "downtime" /monitoring/resources/downtime \
    "{\"downtime\":{\"start_time\":\"$s\",\"end_time\":\"$e\",\"is_fixed\":true,\"duration\":3600,\"comment\":\"gls e2e\",\"with_services\":false},\"resources\":$res}" \
    "EXTERNAL COMMAND: SCHEDULE_SVC_DOWNTIME;$host;gls-crit;"
  realtime "$target" "acknowledgement" /monitoring/resources/acknowledge \
    "{\"acknowledgement\":{\"comment\":\"gls e2e\",\"with_services\":false,\"is_notify_contacts\":false,\"is_persistent_comment\":true,\"is_sticky\":true},\"resources\":$res}" \
    "EXTERNAL COMMAND: ACKNOWLEDGE_SVC_PROBLEM;$host;gls-crit;"
}

# ---------- criterion 2: export + reload ----------

cmd_c2() {
  local target=${1:?central|poller} pid fn svc marker t0 code started_before started_after window
  pid=$(target_poller_id "$target")
  read -r fn svc <<<"$(target_container "$target")"
  login || { record "login" ko; return; }
  marker="gls-marker-$(date +%s)"
  add_host "$marker" "$(sql centreon "SELECT name FROM nagios_server WHERE id=$pid")"
  started_before=$(docker inspect -f '{{.State.StartedAt}}' "$("$fn" ps -q "$svc")")
  t0=$(now)
  code=$(api GET "/configuration/monitoring-servers/$pid/generate-and-reload")
  [[ "$code" == 204 ]] && record "$target: generate-and-reload API" ok "HTTP $code" \
    || record "$target: generate-and-reload API" ko "HTTP $code $(head -c 200 "$LOG_DIR/last.json")"
  if wait_log "$target" "$t0" "Reload configuration finished." 120; then
    window=$(engine_logs "$target" "$t0" -t)
    echo "$window" >"$LOG_DIR/c2-$target-window.log"
    record "$target: reload finished after T0" ok "$(grep -F 'Reload configuration finished.' <<<"$window" | tail -1 | cut -c1-30)"
    grep -qF "Starting to reload configuration." <<<"$window" && grep -qF "Configuration reloaded, main loop continuing." <<<"$window" \
      && record "$target: reload start + success lines" ok || record "$target: reload start + success lines" ko
    grep -qE '\[config\] \[error\]|Error: ' <<<"$window" \
      && record "$target: no config error in the window" ko "$(grep -E '\[config\] \[error\]|Error: ' <<<"$window" | head -2 | tr '\n' ' ')" \
      || record "$target: no config error in the window" ok
  else
    record "$target: reload finished after T0" ko "no 'Reload configuration finished.' in 120 s"
  fi
  "$fn" exec -T "$svc" grep -q "host_name *$marker" /etc/centreon-engine/hosts.cfg \
    && record "$target: marker host in the poller hosts.cfg" ok "$marker" || record "$target: marker host in the poller hosts.cfg" ko "$marker"
  wait_until 120 eval "[[ \$(sql centreon_storage \"SELECT COUNT(*) FROM hosts WHERE name='$marker' AND enabled=1\") -ge 1 ]]" \
    && record "$target: marker host in realtime (centreon_storage.hosts)" ok || record "$target: marker host in realtime" ko
  started_after=$(docker inspect -f '{{.State.StartedAt}}' "$("$fn" ps -q "$svc")")
  [[ "$started_before" == "$started_after" ]] && record "$target: reload without container restart" ok \
    || record "$target: reload without container restart" ko "$started_before -> $started_after"
}

# ---------- criterion 3: disconnected poller ----------

disrupt() {
  local cid; cid=$(cp_ ps -q poller-engine)
  case $1 in
    stop) cp_ stop -t 10 poller-engine ;;
    partition) docker network disconnect gls-wan "$cid" ;;
    pause) docker pause "$cid" ;;
  esac
}
restore() {
  local cid; cid=$(cp_ ps -a -q poller-engine)
  case $1 in
    stop) cp_ start poller-engine ;;
    partition) docker network connect gls-wan "$cid" ;;
    pause) docker unpause "$cid" ;;
  esac
}

is_explicit_error() { # is_explicit_error <mode> <check|reload> <HTTP code> (body in last.json)
  # Decision 1: explicit error, never 2xx, body naming the poller, <= 30 s (checked by the caller).
  # contracts § 4: realtime NOT_CONNECTED=503, TIMEOUT=504 (any mode); 502 = central cbd unreachable.
  if [[ "$2" == check ]]; then
    [[ "$3" == 503 || "$3" == 504 ]] && grep -qF "$POLLER_NAME" "$LOG_DIR/last.json"
    return
  fi
  # Export path: codes not pinned yet (web-sender), any non-2xx except 502 naming the poller.
  [[ "$3" != 2* && "$3" != 502 && "$3" != 000 ]] && grep -qF "$POLLER_NAME" "$LOG_DIR/last.json"
}

cmd_c3() {
  local mode=${1:?stop|partition|pause} host hid sid res t_dis s e code el
  host=gls-hp
  login || { record "login" ko; return; }
  read -r hid sid <<<"$(ids "$host")"
  res="[{\"type\":\"service\",\"id\":$sid,\"parent\":{\"id\":$hid}}]"
  service_ready cp_ poller-engine || { record "$mode: poller connected before" ko; return; }
  # Window of the commands sent while disconnected, by their own timestamp
  # argument (the web puts time() in it): a command sent just before, in the
  # same second as the disruption, must not count as a replay.
  sleep 2
  local dis_epoch restore_epoch replayed
  t_dis=$(now); dis_epoch=$(date +%s)
  disrupt "$mode"
  for what in check reload; do
    s=$(date +%s)
    if [[ $what == check ]]; then
      code=$(API_MAX_TIME=$((MAX_ERROR_DELAY + 15)) api POST /monitoring/resources/check "{\"check\":{\"is_forced\":true},\"resources\":$res}")
    else
      code=$(API_MAX_TIME=$((MAX_ERROR_DELAY + 15)) api GET "/configuration/monitoring-servers/$(poller_id)/generate-and-reload")
    fi
    e=$(date +%s); el=$((e - s))
    cp "$LOG_DIR/last.json" "$LOG_DIR/c3-$mode-$what.json"
    if is_explicit_error "$mode" "$what" "$code" && (( el <= MAX_ERROR_DELAY )); then
      record "$mode: $what rejected explicitly" ok "HTTP $code in ${el} s: $(head -c 150 "$LOG_DIR/last.json")"
    else
      record "$mode: $what rejected explicitly <= ${MAX_ERROR_DELAY} s" ko "HTTP $code in ${el} s: $(head -c 150 "$LOG_DIR/last.json")"
    fi
  done
  restore_epoch=$(date +%s)
  restore "$mode"
  wait_until 180 service_ready cp_ poller-engine && record "$mode: poller reconnected" ok || record "$mode: poller reconnected" ko
  sleep 30
  replayed=$(engine_logs poller "$t_dis" | grep -oE "EXTERNAL COMMAND: SCHEDULE_FORCED_SVC_CHECK;$host;gls-crit;[0-9]+" \
    | awk -F';' -v a="$dis_epoch" -v b="$restore_epoch" '$NF >= a && $NF < b' | head -3 | tr '\n' ' ')
  [[ -n "$replayed" ]] && record "$mode: rejected command not replayed" ko "$replayed" \
    || record "$mode: rejected command not replayed" ok
  realtime poller "$mode: forced check after reconnect" /monitoring/resources/check \
    "{\"check\":{\"is_forced\":true},\"resources\":$res}" "EXTERNAL COMMAND: SCHEDULE_FORCED_SVC_CHECK;$host;gls-crit;"
}

# ---------- state volumes (contracts § 7) ----------

engine_layout() { # engine_layout <compose fn> <service>: prints the retention path, fails with a reason
  "$1" exec -T "$2" sh -c '
    m=/proc/mounts
    grep -q " /var/lib/centreon-engine " $m || { echo "no state volume on /var/lib/centreon-engine"; exit 1; }
    grep -q " /var/lib/centreon-engine/rw tmpfs " $m || { echo "rw is not a tmpfs"; exit 1; }
    ! grep -q " /var/log/centreon-engine " $m || { echo "/var/log/centreon-engine is still a mount"; exit 1; }
    test -p /var/lib/centreon-engine/rw/centengine.cmd || { echo "no command FIFO in rw"; exit 1; }
    grep -h "state_retention_file" /etc/centreon-engine/centengine.cfg | grep -oE "/[^\" ,]+" | head -1' 2>&1
}

svc_downtime_after() { # svc_downtime_after <host> <epoch>: status newer than epoch, still in downtime
  [[ "$(sql centreon_storage "SELECT COUNT(*) FROM services s JOIN hosts h ON h.host_id=s.host_id WHERE h.name='$1' AND s.description='gls-crit' AND s.last_check>$2 AND s.scheduled_downtime_depth>0")" -ge 1 ]]
}

state_files() { # state_files <volume>: listing of a stopped engine's state volume
  docker run --rm --network none -u 0 -v "$1:/s:ro" --entrypoint /bin/ls "$REGISTRY/centreon-engine-trixie:$ENGINE_TAG" -la /s 2>&1
}

cmd_state() {
  local host=gls-hp hid sid res uid out t s e code old new t_re vol files fn svc
  uid=$(sql centreon "SELECT uid FROM nagios_server WHERE name='${POLLER_NAME}'")
  login || { record "login" ko; return; }
  read -r hid sid <<<"$(ids "$host")"
  res="[{\"type\":\"service\",\"id\":$sid,\"parent\":{\"id\":$hid}}]"

  # Layout: state volume, rw on tmpfs with the FIFO, no log volume, retention in the state volume.
  for t in central poller; do
    read -r fn svc <<<"$(target_container "$t")"
    if out=$(engine_layout "$fn" "$svc") && [[ "$out" == /var/lib/centreon-engine/* ]]; then
      record "$t: state volume, rw tmpfs, no log volume, retention in the state volume" ok "$out"
    else
      record "$t: state volume, rw tmpfs, no log volume, retention in the state volume" ko "$(tr '\n' ' ' <<<"$out")"
    fi
  done
  out=$(cc exec -T centreon-broker sh -c 'grep -q " /var/lib/centreon-broker " /proc/mounts && test -w /var/lib/centreon-broker && cat /etc/centreon-broker/central-broker.json' 2>/dev/null \
    | jq -r '.centreonBroker.cache_directory // empty' 2>/dev/null)
  [[ "${out%/}" == /var/lib/centreon-broker ]] && record "central cbd: cache_directory on central-broker-state" ok "$out" \
    || record "central cbd: cache_directory on central-broker-state" ko "cache_directory=${out:-none} or not a writable mount"

  # Retention survives a recreate: downtime set, container recreated, the new
  # engine reports the service still in downtime.
  s=$(date -u +%Y-%m-%dT%H:%M:%S+00:00); e=$(date -u -d '+2 hour' +%Y-%m-%dT%H:%M:%S+00:00); t=$(now)
  code=$(api POST /monitoring/resources/downtime \
    "{\"downtime\":{\"start_time\":\"$s\",\"end_time\":\"$e\",\"is_fixed\":true,\"duration\":7200,\"comment\":\"gls retention\",\"with_services\":false},\"resources\":$res}")
  if [[ "$code" == 2* ]] && wait_log poller "$t" "EXTERNAL COMMAND: SCHEDULE_SVC_DOWNTIME;$host;gls-crit;" 30; then
    record "retention: downtime set on the poller" ok "HTTP $code"
  else
    record "retention: downtime set on the poller" ko "HTTP $code"; return
  fi
  old=$(cp_ ps -q poller-engine)
  # The recreate drops the container logs: keep them for the artifact.
  cp_ logs --no-color --timestamps poller-engine >"$LOG_DIR/poller-before-recreate.log" 2>&1
  # retention.dat is written at shutdown: stop cleanly first (exit 0, not a SIGKILL).
  cp_ stop -t 60 poller-engine >>"$LOG_DIR/state.log" 2>&1
  code=$(docker inspect -f '{{.State.ExitCode}}' "$old" 2>/dev/null)
  [[ "$code" == 0 ]] && record "retention: poller engine stopped cleanly" ok "exit $code" \
    || record "retention: poller engine stopped cleanly" ko "exit ${code:-?}"
  ( set -a; . "$LOG_DIR/poller.env"; set +a; cp_ up -d --force-recreate -t 60 poller-engine ) >>"$LOG_DIR/state.log" 2>&1
  t_re=$(date +%s); new=$(cp_ ps -q poller-engine)
  [[ -n "$new" && "$new" != "$old" ]] && record "retention: poller-engine recreated" ok "${old:0:12} -> ${new:0:12}" \
    || record "retention: poller-engine recreated" ko "${old:0:12} -> ${new:0:12}"
  wait_until 300 poller_capable "$uid" && record "retention: poller reconnected after recreate" ok \
    || { record "retention: poller reconnected after recreate" ko; return; }
  cp_ exec -T poller-engine grep -qF "gls retention" /var/lib/centreon-engine/retention.dat \
    && record "retention: downtime in retention.dat on the state volume" ok || record "retention: downtime in retention.dat on the state volume" ko
  api POST /monitoring/resources/check "{\"check\":{\"is_forced\":true},\"resources\":$res}" >/dev/null
  wait_until 240 svc_downtime_after "$host" "$t_re" \
    && record "retention: service still in downtime after recreate (fresh status)" ok \
    || record "retention: service still in downtime after recreate (fresh status)" ko "$(sql centreon_storage "SELECT s.last_check, s.scheduled_downtime_depth FROM services s JOIN hosts h ON h.host_id=s.host_id WHERE h.name='$host' AND s.description='gls-crit'")"

  # cbmod queue: central cbd down, poller stopped cleanly -> unacked events in
  # <cache_directory>/<broker_name>.{memory,queue}.<output> on the state volume.
  local mod bname cdir qre
  mod=$(poller_module_json)
  bname=$(jq -r '.centreonBroker.broker_name // empty' <<<"$mod" 2>/dev/null)
  cdir=$(jq -r '.centreonBroker.cache_directory // empty' <<<"$mod" 2>/dev/null)
  [[ "${cdir%/}" == /var/lib/centreon-engine && -n "$bname" ]] \
    && record "queue: poller cbmod cache_directory on the state volume" ok "$cdir, broker_name $bname" \
    || { record "queue: poller cbmod cache_directory on the state volume" ko "cache_directory=${cdir:-none} broker_name=${bname:-none}"; return; }
  qre="${bname//./\\.}\\.(memory|queue)\\."
  vol=gls-poller_poller-engine-state
  cc stop -t 30 centreon-broker >>"$LOG_DIR/state.log" 2>&1
  sleep 20
  cp_ stop -t 60 poller-engine >>"$LOG_DIR/state.log" 2>&1
  files=$(state_files "$vol"); echo "$files" >"$LOG_DIR/state-queue-ls.txt"
  grep -qE " $qre" <<<"$files" \
    && record "queue: cbmod failover files in poller-engine-state while the central is down" ok "$(grep -oE "$qre[^ ]+" <<<"$files" | tr '\n' ' ')" \
    || record "queue: cbmod failover files in poller-engine-state while the central is down" ko "$(tr '\n' ' ' <<<"$files" | head -c 300)"
  cc start centreon-broker >>"$LOG_DIR/state.log" 2>&1
  cp_ start poller-engine >>"$LOG_DIR/state.log" 2>&1
  wait_until 300 poller_capable "$uid" && record "queue: poller reconnected after the central restart" ok \
    || { record "queue: poller reconnected after the central restart" ko; return; }
  wait_until 180 eval '! cp_ exec -T poller-engine ls /var/lib/centreon-engine 2>/dev/null | grep -qE "^$qre"' \
    && record "queue: failover files drained after reconnect" ok \
    || record "queue: failover files drained after reconnect" ko "$(cp_ exec -T poller-engine ls -la /var/lib/centreon-engine 2>&1 | tr '\n' ' ' | head -c 300)"
}

# ---------- plugins.json ----------

pkg_ok() { cp_ exec -T poller-engine dpkg-query -W -f '${Status}' "$1" 2>/dev/null | grep -q 'install ok installed'; }

cmd_plugins() {
  # OSS web does not generate plugins.json (module hook): drop one into the
  # generated files between "generate" and "move", like a module would.
  # Generation wipes the cache dir, hence the legacy chain, not generate-and-reload.
  local pkg="${GLS_PLUGIN_PKG:-centreon-plugin-applications-protocol-ftp}" pid
  pid=$(poller_id)
  login || { record "login" ko; return; }
  pkg_ok "$pkg" && { record "$pkg absent from the image before deploy" ko "already installed"; return; }
  record "$pkg absent from the image before deploy" ok
  legacy_xml generateFiles.php "generate=true&debug=true&poller=$pid" \
    && record "legacy generate" ok || { record "legacy generate" ko "$(head -c 200 "$LOG_DIR/legacy-generateFiles.xml")"; return; }
  printf '{"%s": ""}\n' "$pkg" | cc exec -T -u www-data fpm sh -c 'cat > "/var/cache/centreon/config/engine/$0/plugins.json"' "$pid"
  legacy_xml moveFiles.php "poller=$pid" && record "legacy move (deploy, plugins.json included)" ok \
    || record "legacy move (deploy)" ko "$(head -c 200 "$LOG_DIR/legacy-moveFiles.xml")"
  legacy_xml restartPollers.php "mode=1&poller=$pid" && record "legacy reload" ok \
    || record "legacy reload" ko "$(head -c 200 "$LOG_DIR/legacy-restartPollers.xml")"
  wait_until 60 eval "cp_ exec -T poller-engine grep -qF '$pkg' /etc/centreon-engine/plugins.json" \
    && record "plugins.json delivered to the poller" ok || { record "plugins.json delivered to the poller" ko; return; }
  wait_until 600 pkg_ok "$pkg" && record "$pkg installed on the poller (dpkg-query)" ok \
    || record "$pkg installed on the poller (dpkg-query)" ko
}

cmd_logs() {
  { cc ps -a; cp_ ps -a; } >"$LOG_DIR/ps.txt" 2>&1
  cc logs --no-color --timestamps >"$LOG_DIR/central.log" 2>&1
  cp_ logs --no-color --timestamps >"$LOG_DIR/poller.log" 2>&1
  sql centreon "SELECT id,name,localhost,poller_type,ns_activate FROM nagios_server" >"$LOG_DIR/nagios_server.tsv" 2>&1
  sql centreon_storage "SELECT instance_id,name,running,deleted,last_alive FROM instances" >"$LOG_DIR/instances.tsv" 2>&1
  return 0
}

cmd_down() { # only the gls projects this script started
  cp_ down -v --remove-orphans >/dev/null 2>&1
  cc down -v --remove-orphans >/dev/null 2>&1
  docker network rm gls-wan >/dev/null 2>&1
  return 0
}

cmd=${1:-}; shift || true
case "$cmd" in
  up) cmd_up ;;
  step0) cmd_step0 ;;
  c1) cmd_c1 "$@" ;;
  c2) cmd_c2 "$@" ;;
  c3) cmd_c3 "$@" ;;
  plugins) cmd_plugins ;;
  ca) cmd_ca ;;
  tls-negative) cmd_tls_negative ;;
  state) cmd_state ;;
  logs) cmd_logs; exit 0 ;;
  down) cmd_down; exit 0 ;;
  *) sed -n '2,17p' "$0"; exit 2 ;;
esac
summary "$cmd $*"
exit $(( failures > 0 ))
