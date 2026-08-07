#!/bin/bash
#
# Config/wiring test for the centreon-centreontrapd / centreon-snmptrapd
# product Docker images. Boots both containers together (sharing the spool
# volume like a real deployment), sends a real SNMP trap into snmptrapd, and
# checks the full trap-forwarding path end to end: snmptrapd receives it and
# writes it to the shared spool, then centreontrapd picks it up and removes
# it from the spool once processed.
set -e

# shellcheck source=.github/scripts/lib/centreon-trapd-common.sh
source .github/scripts/lib/centreon-trapd-common.sh

CENTREONTRAPD_IMAGE="${CENTREONTRAPD_IMAGE:?ERROR: CENTREONTRAPD_IMAGE env var must be set}"
SNMPTRAPD_IMAGE="${SNMPTRAPD_IMAGE:?ERROR: SNMPTRAPD_IMAGE env var must be set}"
COMPOSE_FILE=".github/docker/centreon-trapd/docker-compose.config-wiring-test.yml"
PROJECT="centreon-trapd-config-wiring-test"
COMPOSE=(docker compose -f "$COMPOSE_FILE" -p "$PROJECT")
READY_TIMEOUT="${READY_TIMEOUT:-60}"
TRAP_TIMEOUT="${TRAP_TIMEOUT:-15}"

CENTREONTRAPD_SDB_FIXTURE=$(build_centreontrapd_sdb_fixture "centreontrapd-wiring-test") || exit 1

export CENTREONTRAPD_IMAGE SNMPTRAPD_IMAGE CENTREONTRAPD_SDB_FIXTURE

cleanup() {
  "${COMPOSE[@]}" logs > /tmp/centreon-trapd-config-wiring-test.log 2>&1 || true
  "${COMPOSE[@]}" down -v --remove-orphans > /dev/null 2>&1 || true
  rm -f "$CENTREONTRAPD_SDB_FIXTURE" || true
}
trap cleanup EXIT

# /tmp/docker.ready is touched by container.d/99-logs.sh, the last entrypoint
# script, right before the real daemon is exec'd.
wait_ready() {
  local service="$1" timeout="${2:-$READY_TIMEOUT}"
  for _ in $(seq 1 "$timeout"); do
    if "${COMPOSE[@]}" exec -T "$service" test -f /tmp/docker.ready 2>/dev/null; then
      return 0
    fi
    sleep 1
  done
  echo "::error::$service did not report readiness (/tmp/docker.ready) within ${timeout}s"
  "${COMPOSE[@]}" logs "$service" || true
  return 1
}

wait_for_log() {
  local service="$1" pattern="$2" timeout="${3:-$TRAP_TIMEOUT}"
  for _ in $(seq 1 "$timeout"); do
    if "${COMPOSE[@]}" logs "$service" 2>&1 | grep -q "$pattern"; then
      return 0
    fi
    sleep 1
  done
  return 1
}

echo "=== [wiring] Starting centreontrapd + snmptrapd ==="
"${COMPOSE[@]}" up -d

echo "=== [wiring] Waiting for both containers to report readiness ==="
wait_ready centreontrapd || exit 1
wait_ready snmptrapd || exit 1

for service in centreontrapd snmptrapd; do
  running=$(docker inspect -f '{{.State.Running}}' "${PROJECT}-${service}-1" 2>/dev/null || echo false)
  if [ "$running" != "true" ]; then
    echo "::error::$service container is not running after startup"
    "${COMPOSE[@]}" logs "$service" || true
    exit 1
  fi
done

# Confirm snmptrapd is actually listening before sending anything - UDP has
# no handshake, so a trap sent before the daemon binds the port is silently
# dropped and the test would flake instead of failing meaningfully.
echo "=== [wiring] Checking snmptrapd is listening ==="
if ! wait_for_log snmptrapd "Listening on UDP/162"; then
  echo "::error::snmptrapd never logged its listening banner"
  "${COMPOSE[@]}" logs snmptrapd || true
  exit 1
fi
if "${COMPOSE[@]}" logs snmptrapd 2>&1 | grep -Ei "Compilation failed|Can't locate|Segmentation fault|Out of memory"; then
  echo "::error::snmptrapd logs contain a crash signature, see above"
  exit 1
fi

echo "=== [wiring] Sending a real SNMP trap to snmptrapd (UDP/1620) ==="
snmptrap -v2c -c public 127.0.0.1:1620 '' \
  1.3.6.1.4.1.2021.13.990.1 \
  1.3.6.1.4.1.2021.13.990.1.1 s "e2e wiring test"

echo "=== [wiring] Checking snmptrapd forwarded the trap without crashing ==="
if "${COMPOSE[@]}" logs snmptrapd 2>&1 | grep -Ei "Compilation failed|Can't locate|Segmentation fault|Out of memory"; then
  echo "::error::snmptrapd logs contain a crash signature, see above"
  exit 1
fi

echo "=== [wiring] Checking the trap landed in the shared spool directory ==="
spool_ok=false
for _ in $(seq 1 "$TRAP_TIMEOUT"); do
  count=$("${COMPOSE[@]}" exec -T centreontrapd sh -c 'ls -1 /var/spool/centreontrapd | wc -l' | tr -d '\r')
  if [ "${count:-0}" -gt 0 ]; then
    spool_ok=true
    break
  fi
  sleep 1
done
if [ "$spool_ok" != "true" ]; then
  echo "::error::no trap file appeared under the shared /var/spool/centreontrapd volume"
  "${COMPOSE[@]}" logs || true
  exit 1
fi

# The image's default --severity=error hides this - the compose file starts
# centreontrapd with --severity=debug specifically so this trace is visible.
echo "=== [wiring] Checking centreontrapd's debug log shows it picked up the trap file ==="
if ! wait_for_log centreontrapd "Processing file:"; then
  echo "::error::centreontrapd never logged 'Processing file:' - it did not pick up the trap from the spool"
  "${COMPOSE[@]}" logs centreontrapd || true
  exit 1
fi

# centreontrapd's default policy_trap=1 deletes the spool file once it has
# been processed - even for an unknown trap (no matching row in `traps`),
# unlink_trap stays at its default of 1 unless the DB connection itself
# drops. So the file disappearing again proves centreontrapd actually
# consumed it, not just that it was sitting in the spool untouched.
echo "=== [wiring] Checking centreontrapd consumed (removed) the trap from the spool ==="
consumed_ok=false
for _ in $(seq 1 "$TRAP_TIMEOUT"); do
  count=$("${COMPOSE[@]}" exec -T centreontrapd sh -c 'ls -1 /var/spool/centreontrapd | wc -l' | tr -d '\r')
  if [ "${count:-1}" -eq 0 ]; then
    consumed_ok=true
    break
  fi
  sleep 1
done
if [ "$consumed_ok" != "true" ]; then
  echo "::error::trap file was never removed from the spool - centreontrapd did not process it"
  "${COMPOSE[@]}" logs centreontrapd || true
  exit 1
fi

echo "=== [wiring] Checking centreontrapd is still running and did not crash ==="
running=$(docker inspect -f '{{.State.Running}}' "${PROJECT}-centreontrapd-1" 2>/dev/null || echo false)
if [ "$running" != "true" ]; then
  echo "::error::centreontrapd exited after the trap was forwarded to its spool"
  "${COMPOSE[@]}" logs centreontrapd || true
  exit 1
fi
if "${COMPOSE[@]}" logs centreontrapd 2>&1 | grep -Ei "Compilation failed|Can't locate|Segmentation fault|Out of memory"; then
  echo "::error::centreontrapd logs contain a crash signature, see above"
  exit 1
fi

echo "=== [wiring] PASSED ==="
