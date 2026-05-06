#!/bin/sh

if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

BASEDIR="/usr/local/lib/centreon-snmptrapd/container.d"
for file in $(find "$BASEDIR" -maxdepth 1 -type f | xargs -n1 basename | sort); do
  case "$file" in
    *_background*)
      if . "$BASEDIR/$file" > /tmp/bg_${file}.log & then
        pid=$!
        echo $pid >> /tmp/background_pids
      else
        echo "Error starting background script $file"
        exit 1
      fi
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
