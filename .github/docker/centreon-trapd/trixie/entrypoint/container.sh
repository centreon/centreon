#!/bin/sh

if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

# Role is picked by the command the caller (compose/chart) passes as CMD, not
# by a dedicated env var: snmptrapd's CMD contains the literal "snmptrapd"
# binary name, centreontrapd's never does.
case "$1" in
  *snmptrapd*) ROLE=snmptrapd ;;
  *) ROLE=centreontrapd ;;
esac
echo "=== Role: ${ROLE} ==="

# Override the --severity flag baked into the image's CMD with CENTREONTRAPD_LOG_LEVEL.
# No-op for the snmptrapd role: its CMD has no --severity= arg to match.
# Must run before container.d/99-logs.*.sh, which is sourced (not forked) below
# and itself execs "$@" — anything placed after the loop never runs.
if [ -n "${CENTREONTRAPD_LOG_LEVEL}" ]; then
  remaining=$#
  while [ "$remaining" -gt 0 ]; do
    arg=$1
    shift
    case "$arg" in
      --severity=*) arg="--severity=${CENTREONTRAPD_LOG_LEVEL}" ;;
    esac
    set -- "$@" "$arg"
    remaining=$((remaining - 1))
  done
fi

BASEDIR="/usr/local/lib/centreon-trapd/container.d"
for file in $(find "$BASEDIR" -maxdepth 1 -type f | xargs -n1 basename | sort); do
  # Files named *.snmptrapd.sh / *.centreontrapd.sh only apply to that role —
  # skip them outright (never sourced, never forked) when it doesn't match.
  # Unsuffixed files (e.g. 00-init.sh) apply to both roles.
  case "$file" in
    *.snmptrapd.sh) [ "$ROLE" = "snmptrapd" ] || continue ;;
    *.centreontrapd.sh) [ "$ROLE" = "centreontrapd" ] || continue ;;
  esac
  case "$file" in
    *_background*)
      . "$BASEDIR/$file" > /proc/1/fd/1 2>/proc/1/fd/2 &
      pid=$!
      sleep 1
      if ! kill -0 "$pid" 2>/dev/null; then
        echo "Error starting background script $file"
        exit 1
      fi
      echo $pid >> /tmp/background_pids
      ;;
    *)
      if ! . "$BASEDIR/$file"; then
        echo "Error executing $file"
        exit 1
      fi
      ;;
  esac
done

exec "$@"
