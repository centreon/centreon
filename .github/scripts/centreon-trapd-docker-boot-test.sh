#!/bin/bash
#
# Boot test for the centreon-trapd product Docker image, one role at a time.
# Starts the image standalone in the given role (CMD keyword "snmptrapd" or
# "centreontrapd") with default (out-of-the-box) settings and checks that the
# container boots cleanly, runs as the expected non-root user, and does not
# crash.
set -e

# shellcheck source=.github/scripts/lib/centreon-trapd-common.sh
source .github/scripts/lib/centreon-trapd-common.sh

IMAGE="${IMAGE:?ERROR: IMAGE env var must be set to the image reference to test}"
ROLE="${ROLE:?ERROR: ROLE env var must be set (snmptrapd or centreontrapd)}"
PLATFORM="${PLATFORM:-}"
CONTAINER_NAME="centreon-trapd-${ROLE}-runtime-test-$$"
READY_TIMEOUT="${READY_TIMEOUT:-60}"
EXPECTED_UID=900 # centreon user, same for both roles
SDB_TMP=""

platform_args=()
if [ -n "$PLATFORM" ]; then
  platform_args=(--platform "$PLATFORM")
fi

run_args=()
case "$ROLE" in
  snmptrapd)
  # snmptrapd binds UDP/162 as the non-root "centreon" (uid 900) user and
  # needs CAP_NET_BIND_SERVICE at runtime to do so (see the Dockerfile).
  run_args=(--cap-add=NET_BIND_SERVICE)
  ;;
  centreontrapd)
  SDB_TMP=$(build_centreontrapd_sdb_fixture "centreontrapd-boot-test") || exit 1
  run_args=(-v "${SDB_TMP}:/etc/snmp/centreon_traps/centreontrapd.sdb")
  ;;
  *)
  echo "::error::unknown ROLE '$ROLE' (expected snmptrapd or centreontrapd)"
  exit 1
  ;;
esac

cleanup() {
  local rc=$?
  docker logs "$CONTAINER_NAME" > /tmp/centreon-trapd-boot-test.log 2>&1 || true
  docker rm -f "$CONTAINER_NAME" > /dev/null 2>&1 || true
  [ -n "$SDB_TMP" ] && rm -f "$SDB_TMP" || true
  _summary_render "Boot test — centreon-trapd ${ROLE}-${PLATFORM:-default}" "$rc"
}
trap cleanup EXIT

# /tmp/docker.ready is touched by container.d/99-logs.<role>.sh, the last entrypoint
# script, right before the real daemon is exec'd.
wait_ready() {
  local container="$1" timeout="${2:-$READY_TIMEOUT}"
  for _ in $(seq 1 "$timeout"); do
    if docker exec "$container" test -f /tmp/docker.ready 2>/dev/null; then
      return 0
    fi
    sleep 1
  done
  echo "::error::$container did not report readiness (/tmp/docker.ready) within ${timeout}s"
  docker logs "$container" || true
  return 1
}

summary_step_start "Container starts"
echo "=== [boot] Starting $IMAGE as $ROLE ${PLATFORM:+(platform: $PLATFORM)} ==="
docker run -d --name "$CONTAINER_NAME" "${platform_args[@]}" "${run_args[@]}" "$IMAGE" "$ROLE"
summary_step_pass

summary_step_start "Reports readiness (/tmp/docker.ready)"
echo "=== [boot] Waiting for /tmp/docker.ready (timeout: ${READY_TIMEOUT}s) ==="
wait_ready "$CONTAINER_NAME" || exit 1
echo "Container is ready."
summary_step_pass

summary_step_start "Still running after startup"
echo "=== [boot] Checking container is still running ==="
running=$(docker inspect -f '{{.State.Running}}' "$CONTAINER_NAME")
if [ "$running" != "true" ]; then
  echo "::error::$ROLE container is not running after startup"
  docker logs "$CONTAINER_NAME" || true
  exit 1
fi
summary_step_pass

summary_step_start "Runs as non-root uid $EXPECTED_UID (centreon)"
echo "=== [boot] Checking non-root user (expected uid $EXPECTED_UID, centreon) ==="
uid=$(docker exec "$CONTAINER_NAME" id -u)
if [ "$uid" != "$EXPECTED_UID" ]; then
  echo "::error::$ROLE process runs as uid $uid, expected $EXPECTED_UID (centreon)"
  exit 1
fi
summary_step_pass

summary_step_start "No crash signature in logs"
echo "=== [boot] Scanning logs for unambiguous crash signatures ==="
if docker logs "$CONTAINER_NAME" 2>&1 | grep -Ei "Compilation failed|Can't locate|Segmentation fault|Out of memory"; then
  echo "::error::$ROLE logs contain a crash signature, see above"
  exit 1
fi
summary_step_pass

summary_step_start "Stops cleanly"
echo "=== [boot] Stopping container (validates entrypoint cleanup) ==="
if ! docker stop "$CONTAINER_NAME" > /dev/null; then
  echo "::error::$ROLE container did not stop cleanly within the default timeout"
  exit 1
fi
summary_step_pass

echo "=== [boot] PASSED for $IMAGE as $ROLE ${PLATFORM:+(platform: $PLATFORM)} ==="
