#!/usr/bin/env bash
# End-to-end smoke test of the split central compose stack with CI-built images.
#
# Every compose service built from the split Dockerfile (build.target httpd,
# fpm or cron) is pointed at the matching CI image through a generated
# override, so services added later in the PoC stack (gorgone-central,
# autodiscovery worker, ...) are covered without changing this script.
# Assertions only run when the service they need exists on the branch.
#
# Env:
#   IMAGE_TEMPLATE  CI image of a build target, with "{target}" as placeholder
#                   (e.g. registry/centreon-web-split-{target}-trixie:<tag>)
#   GORGONE_IMAGE, BROKER_IMAGE, ENGINE_IMAGE, TRAPD_IMAGE  dependency images
#   COMPOSE_OVERLAY optional extra compose file layered on the base stack
#                   (e.g. the database TLS overlay docker-compose.db-tls.yml)
#   SMOKE_VARIANT  label of this run in the summary (default "plain")
#   SUMMARY_FILE   optional markdown summary output
#   WAIT_TIMEOUT   seconds to wait for the stack to be healthy (default 1200)
set -euo pipefail

: "${IMAGE_TEMPLATE:?}"
export GORGONE_IMAGE BROKER_IMAGE ENGINE_IMAGE TRAPD_IMAGE
SUMMARY_FILE="${SUMMARY_FILE:-/dev/null}"
WAIT_TIMEOUT="${WAIT_TIMEOUT:-1200}"
LOG_DIR="${LOG_DIR:-/tmp/centreon-central-smoke}"
mkdir -p "$LOG_DIR"

SPLIT_DIR=.github/docker/centreon-web-split
BASE="$SPLIT_DIR/docker-compose.yml"
OVERRIDE="$SPLIT_DIR/docker-compose.ci-images.yml"
PROJECT=central-smoke
SMOKE_VARIANT="${SMOKE_VARIANT:-plain}"
compose_files=(-f "$BASE")
[[ -n "${COMPOSE_OVERLAY:-}" ]] && compose_files+=(-f "$COMPOSE_OVERLAY")
compose() { docker compose -p "$PROJECT" "${compose_files[@]}" -f "$OVERRIDE" "$@"; }

results=()
failures=0
record() {
  if [[ "$2" == ok ]]; then results+=("| $1 | :white_check_mark: |"); else
    results+=("| $1 | :x: |"); failures=$((failures + 1)); echo "::error::smoke: $1 failed"; fi
}

write_summary() {
  {
    echo "### Central compose smoke test (${SMOKE_VARIANT})"
    echo
    echo "| Check | Result |"
    echo "| --- | --- |"
    printf '%s\n' "${results[@]}"
    echo
  } >>"$SUMMARY_FILE"
}

cleanup() {
  compose ps -a >"$LOG_DIR/ps.txt" 2>&1 || true
  compose logs --no-color --timestamps >"$LOG_DIR/compose.log" 2>&1 || true
  compose down -v --remove-orphans >/dev/null 2>&1 || true
  rm -f "$OVERRIDE"
  write_summary
}
trap cleanup EXIT

# Override: image per build target, never build locally.
docker compose -f "$BASE" config --format json >"$LOG_DIR/base-config.json"
# Any build target of the split Dockerfile maps to its CI image, so a new target
# (rrdcached, ...) needs no change here.
jq --arg tpl "$IMAGE_TEMPLATE" '
  {services: (.services
    | with_entries(select(.value.build.target? != null))
    | with_entries(.value.build.target as $t
        | .value = {image: ($tpl | split("{target}") | join($t)), pull_policy: "always"}))
  }' "$LOG_DIR/base-config.json" >"$OVERRIDE"
echo "Image override:"; cat "$OVERRIDE"

services=$(compose config --services)
has_service() { grep -qx "$1" <<<"$services"; }

compose pull --quiet --ignore-buildable
# A service can fail to be created while the stack converges (rrdcached joins the
# broker's network namespace, which disappears while the broker restarts), so
# `up -d` is re-applied on every poll round and a first failure is not fatal.
compose up -d --no-build || echo "compose up returned non-zero, retrying while polling"

# Not `up --wait`: it aborts as soon as a container restarts, and some services
# restart by design while the stack converges (centreon-broker exits until fpm
# has delivered its configuration through gorgone). Poll instead: every service
# must end up running, and healthy when it defines a healthcheck.
service_ready() {
  local id state health
  id=$(compose ps -a -q "$1" 2>/dev/null | head -1)
  [ -n "$id" ] || return 1
  state=$(docker inspect -f '{{.State.Status}}' "$id" 2>/dev/null)
  health=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "$id" 2>/dev/null)
  # One-shot init services (db-tls-init, ...) are done once they exited 0.
  if [ "$state" = exited ]; then
    case "$(docker inspect -f '{{.HostConfig.RestartPolicy.Name}}:{{.State.ExitCode}}' "$id" 2>/dev/null)" in no:0|:0) return 0 ;; *) return 1 ;; esac
  fi
  [ "$state" = running ] && { [ -z "$health" ] || [ "$health" = healthy ]; }
}
deadline=$(( $(date +%s) + WAIT_TIMEOUT ))
while :; do
  pending=()
  while read -r svc; do
    service_ready "$svc" || pending+=("$svc")
  done <<<"$services"
  (( ${#pending[@]} == 0 )) && break
  if (( $(date +%s) >= deadline )); then
    echo "::error::not ready after ${WAIT_TIMEOUT}s: ${pending[*]}"
    record "stack healthy (not ready: ${pending[*]})" ko
    exit 1
  fi
  echo "waiting for: ${pending[*]}"
  compose up -d --no-build >/dev/null 2>&1 || true
  sleep 15
done
record "stack healthy ($(wc -l <<<"$services") services)" ok

# REST login through httpd -> php-fpm, with the install's default admin.
port=$(compose port httpd 80 | awk -F: '{print $NF}')
login_status=$(curl -s -o "$LOG_DIR/login.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' \
  -d '{"security":{"credentials":{"login":"admin","password":"Centreon!2021"}}}' \
  "http://127.0.0.1:${port}/centreon/api/latest/login" || true)
if [[ "$login_status" == 200 ]] && jq -e '.security.token | length > 0' "$LOG_DIR/login.json" >/dev/null; then
  record "REST login (admin)" ok
else
  record "REST login (admin), HTTP ${login_status}" ko
fi

# Static asset served by httpd itself (not proxied to php-fpm).
if curl -sf -o /dev/null "http://127.0.0.1:${port}/centreon/static/" -I || \
   curl -sf -o /dev/null "http://127.0.0.1:${port}/centreon/"; then
  record "httpd serves /centreon/" ok
else
  record "httpd serves /centreon/" ko
fi

# Autodiscovery save-worker: only once a service runs it.
worker=$(jq -r '.services | to_entries[] | select((.value.command // []) | tostring | test("save-worker")) | .key' \
  "$LOG_DIR/base-config.json" | head -1)
if [[ -n "$worker" ]]; then
  if compose exec -T -u www-data fpm php /usr/share/centreon/bin/console list centreon-autodiscovery 2>/dev/null | grep -q save-worker; then
    record "autodiscovery save-worker command available" ok
  else
    record "autodiscovery save-worker command available" ko
  fi
  if [[ "$(compose ps --format '{{.State}}' "$worker")" == running ]]; then
    record "service ${worker} running" ok
  else
    record "service ${worker} running" ko
  fi
fi

# Gorgone running as the central poller: its healthcheck (not a log grep, whose
# wording varies between gorgone builds) once the service exists.
if has_service gorgone-central; then
  if service_ready gorgone-central; then
    record "gorgone-central healthy" ok
  else
    record "gorgone-central healthy" ko
  fi
fi

# PHP sessions in Valkey: only once a valkey service exists. A UI login (session
# cookie, not the REST token) must still work after fpm is recreated: internal.php
# answers 401 unless $_SESSION['centreon'] is still in the session store.
if has_service valkey; then
  jar="$LOG_DIR/session.cookies"
  session_call() {
    curl -s -o /dev/null -w '%{http_code}' -b "$jar" \
      "http://127.0.0.1:${port}/centreon/api/internal.php?object=centreon_topcounter&action=user" || true
  }
  curl -s -o /dev/null -c "$jar" -H 'Content-Type: application/json' \
    -d '{"login":"admin","password":"Centreon!2021"}' \
    "http://127.0.0.1:${port}/centreon/api/latest/authentication/providers/configurations/local" || true
  before=$(session_call)
  keys=$(compose exec -T valkey sh -c 'REDISCLI_AUTH="$VALKEY_PASSWORD" valkey-cli --scan --pattern "PHPREDIS_SESSION:*" | wc -l' 2>/dev/null | tr -d '[:space:]' || true)
  if [[ "$before" == 200 && "${keys:-0}" -gt 0 ]]; then
    record "UI session stored in valkey (${keys} key(s))" ok
  else
    record "UI session stored in valkey (HTTP ${before}, ${keys:-0} key(s))" ko
  fi
  compose up -d --no-build --force-recreate fpm >/dev/null 2>&1 || true
  deadline=$(( $(date +%s) + 300 ))
  until service_ready fpm || (( $(date +%s) >= deadline )); do sleep 5; done
  after=$(session_call)
  if [[ "$after" == 200 ]]; then
    record "UI session survives fpm recreate" ok
  else
    record "UI session survives fpm recreate (HTTP ${after})" ko
  fi
fi

# Database TLS overlay: MariaDB runs with require_secure_transport, so every
# check above already went through TLS clients. Prove the server side too, and
# run the Perl report jobs once (centreon::common::db, db_ssl* from conf.pm).
if has_service db-tls-init; then
  if compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb --skip-ssl -h 127.0.0.1 -uroot -e "SELECT 1"' >/dev/null 2>&1; then
    record "db refuses a non-TLS login" ko
  else
    record "db refuses a non-TLS login" ok
  fi
  cipher=$(compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb --ssl --ssl-ca=/certs/ca/ca.pem -h 127.0.0.1 -uroot -N -s -e "SHOW SESSION STATUS LIKE '"'"'Ssl_cipher'"'"'"' 2>/dev/null | awk '{print $2}' | tr -d '[:space:]' || true)
  if [[ -n "$cipher" ]]; then
    record "db TLS session (${cipher})" ok
  else
    record "db TLS session" ko
  fi
  for job in eventReportBuilder dashboardBuilder; do
    if compose exec -T --user centreon centreon-cron /usr/share/centreon/cron/"$job" --config=/etc/centreon/conf.pm         >"$LOG_DIR/${job}.log" 2>&1; then
      record "cron ${job} over TLS" ok
    else
      record "cron ${job} over TLS" ko
    fi
  done
fi

(( failures == 0 ))
