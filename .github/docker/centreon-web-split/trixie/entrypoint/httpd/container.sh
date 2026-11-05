#!/bin/sh

if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

BASEDIR="$(CDPATH= cd -- "$(dirname -- "$0")/container.d" && pwd)"
for file in $(find "$BASEDIR" -maxdepth 1 -type f | xargs -n1 basename | sort); do
  case "$file" in
    *_background*)
      . "$BASEDIR/$file" > /proc/1/fd/1 2>/proc/1/fd/2 &
      pid=$!
      sleep 1
      # Already gone after 1s: only an error if it failed. A background
      # script that is disabled by its own gate exits 0 on purpose.
      if ! kill -0 "$pid" 2>/dev/null; then
        if wait "$pid"; then
          continue
        fi
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
