#!/bin/sh

if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

# Override the --severity flag baked into the image's CMD with CENTREONTRAPD_LOG_LEVEL.
# Must run before container.d/99-logs.sh, which is sourced (not forked) below and
# itself execs "$@" — anything placed after the loop never runs.
if [ -n "${CENTREONTRAPD_LOG_LEVEL}" ]; then
  new_args=""
  for arg in "$@"; do
    case "$arg" in
      --severity=*) arg="--severity=${CENTREONTRAPD_LOG_LEVEL}" ;;
    esac
    new_args="${new_args} ${arg}"
  done
  set -- ${new_args}
fi

BASEDIR="/usr/local/lib/centreon-centreontrapd/container.d"
for file in $(find "$BASEDIR" -maxdepth 1 -type f | xargs -n1 basename | sort); do
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
