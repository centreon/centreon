#!/usr/bin/env bash
# Gorgone-less light Central PoC (MON-215451): stack driver and e2e harness.
#
#   gls-e2e.sh up                 start gls-central + gls-poller, bootstrap, seed the poller
#   gls-e2e.sh step0              no gorgone, upstream data in centreon_storage, poller outbound-only
#   gls-e2e.sh c1 central|poller  realtime actions (forced check, downtime, ack) in centengine.log
#   gls-e2e.sh c2 central|poller  export + reload applied (marker host, no error, after T0)
#   gls-e2e.sh c3 stop|partition|pause   explicit error <= 30 s on a disconnected poller, no replay
#   gls-e2e.sh plugins            packages listed in the poller plugins.json get installed
#   gls-e2e.sh logs | down
#
# Env: GLS_REGISTRY, GLS_WEB_TAG, GLS_ENGINE_TAG, GLS_BROKER_TAG override images.env;
# LOG_DIR (default /tmp/gls-e2e), SUMMARY_FILE (markdown summary, default none),
# GLS_SEED_POLLER=1 (default): copy the web-generated poller config out of band
# (step 0, no downstream channel yet). Set 0 once the BBDO channel delivers it.
set -uo pipefail

GLS_DIR=$(cd "$(dirname "$0")/.." && pwd)
set -a
# shellcheck disable=SC1091
. "$GLS_DIR/images.env"
REGISTRY="${GLS_REGISTRY:-$REGISTRY}"
WEB_TAG="${GLS_WEB_TAG:-$WEB_TAG}"
ENGINE_TAG="${GLS_ENGINE_TAG:-$ENGINE_TAG}"
BROKER_TAG="${GLS_BROKER_TAG:-$BROKER_TAG}"
HTTPD_PORT="${HTTPD_PORT:-18180}"
POLLER_NAME="${POLLER_NAME:-gls-poller}"
set +a

LOG_DIR="${LOG_DIR:-/tmp/gls-e2e}"
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
  curl "${args[@]}" "$API$path" || echo 000
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

# Until the web generator writes them (contracts § 1, § 2), GLS_PATCH_REMOTE=1:
# remote_api on the central cbd, remote_actions(+insecure, no TLS yet) on cbmod.
patch_keys() { # patch_keys api|actions <glob evaluated inside fpm>
  cc exec -T -u www-data fpm sh -c "php -- $1 $2" <"$(dirname "$0")/patch-remote-keys.php" >>"$LOG_DIR/patch-keys.log" 2>&1
}

seed_poller() { # step 0 only: out-of-band copy of the web-generated poller config
  local pid=$1
  clapi -a POLLERGENERATE -v "$pid" >"$LOG_DIR/pollergenerate.txt" 2>&1
  [[ "${GLS_PATCH_REMOTE:-1}" == 1 ]] && { patch_keys actions "/var/cache/centreon/config/broker/$pid/*-module.json" || return 1; }
  local kind
  for kind in engine broker; do
    # Files only: a "." entry makes tar chmod the volume root, which it does not own.
    cc exec -T fpm sh -c 'cd "/var/cache/centreon/config/$1/$0" && tar -cf - -- *' "$pid" "$kind" \
      | cp_ exec -T poller-engine tar -C "/etc/centreon-$kind" -xf - || return 1
  done
}

# Legacy export chain (what the UI export page calls), driven over HTTP with
# the REST token: generate, optional hook on the generated files, move, reload.
legacy_xml() { # legacy_xml <script> <body> -> 0 when statuscode is 0
  local out="$LOG_DIR/legacy-${1%.php}.xml"
  curl -s -o "$out" --max-time 180 -H "X-AUTH-TOKEN: $TOKEN" -d "$2" \
    "http://127.0.0.1:${HTTPD_PORT}/centreon/include/configuration/configGenerate/xml/$1" || return 1
  grep -qF -e "<statuscode><![CDATA[0]]></statuscode>" -e "<statuscode>0</statuscode>" "$out"
}

use_broker_channel() { # web-sender images only: per-poller channel switch (contracts § 4)
  if [[ -n "$(sql centreon "SHOW COLUMNS FROM nagios_server LIKE 'remote_channel'")" ]]; then
    sql centreon "UPDATE nagios_server SET remote_channel='broker' WHERE localhost='1' OR name='${POLLER_NAME}'"
    record "remote_channel=broker for Central and ${POLLER_NAME}" ok
  else
    record "remote_channel column absent (step 0 images), channel unchanged" ok
  fi
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

  local pid
  pid=$(poller_id)
  if [[ -z "$pid" ]]; then
    api POST /administration/tokens '{"name":"gls-poller-token","type":"poller","expiration_date":null}' >/dev/null
    local code
    code=$(api POST /configuration/pollers "{\"name\":\"${POLLER_NAME}\",\"poller_type\":\"vm\",\"address\":\"poller-engine\",\"poller_token_name\":\"gls-poller-token\",\"central_address\":\"centreon-broker\"}")
    cp "$LOG_DIR/last.json" "$LOG_DIR/create-poller.json"
    pid=$(poller_id)
    [[ -n "$pid" ]] && record "create poller ${POLLER_NAME}" ok "HTTP $code id=$pid" || { record "create poller" ko "HTTP $code"; return 1; }
  fi

  add_service_template
  local h
  for h in "gls-hc;Central" "gls-hp;${POLLER_NAME}"; do
    add_host "${h%%;*}" "${h#*;}"
    add_service "${h%%;*}" && record "fixture ${h%%;*}/gls-crit" ok || record "fixture ${h%%;*}/gls-crit" ko "see clapi.log"
  done

  local code
  code=$(api GET /configuration/monitoring-servers/1/generate-and-reload)
  [[ "$code" == 204 ]] && record "central generate + local copy" ok "HTTP $code" \
    || record "central generate + local copy" ko "HTTP $code $(head -c 300 "$LOG_DIR/last.json")"
  if [[ "${GLS_PATCH_REMOTE:-1}" == 1 ]]; then
    if patch_keys api /etc/centreon-broker/central-broker.json && patch_keys actions "/etc/centreon-broker/*-module.json"; then
      # cbd and cbmod read these at start: restart them on the patched files.
      cc restart centreon-broker central-engine >/dev/null 2>&1
      record "remote_api / remote_actions patched (until the web generates them)" ok
    else
      record "remote_api / remote_actions patched" ko "see patch-keys.log"
    fi
  fi

  cp_ up -d || true
  if [[ "${GLS_SEED_POLLER:-1}" == 1 ]]; then
    wait_until 300 service_ready_running cp_ poller-engine
    seed_poller "$pid" && record "poller config seeded (out of band, step 0)" ok "poller id $pid" \
      || record "poller config seeded" ko
  fi
  wait_until 600 service_ready cc centreon-broker && record "centreon-broker running" ok || record "centreon-broker running" ko
  wait_until 600 service_ready cp_ poller-engine && record "poller connected to centreon-broker:5669" ok \
    || record "poller connected to centreon-broker:5669" ko
  # The first central delivery had to be the local copy (cbd not running before it).
  use_broker_channel
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
  # contracts § 4: realtime NOT_CONNECTED=503, TIMEOUT=504; 502 = central cbd unreachable (not this case).
  if [[ "$2" == check ]]; then
    case "$1:$3" in
      stop:503 | partition:503 | partition:504 | pause:503 | pause:504) return 0 ;;
      *) return 1 ;;
    esac
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
  logs) cmd_logs; exit 0 ;;
  down) cmd_down; exit 0 ;;
  *) sed -n '2,16p' "$0"; exit 2 ;;
esac
summary "$cmd $*"
exit $(( failures > 0 ))
