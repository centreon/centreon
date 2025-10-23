#!/bin/sh

# Only enable debug mode if requested
if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

# Run each startup script located in BASEDIR.
# ls is required to ensure that the scripts are properly sorted by name.
BASEDIR="/var/lib/centreon-gorgone/container.d"
for file in $(find "$BASEDIR" -maxdepth 1 -type f -printf '%f\n' | sort); do
  case "$file" in
    *_background*)
      # Execute background script and store PID
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

cleanup() {
  # Read and terminate background processes
  if [ -f /tmp/background_pids ]; then
    while read -r pid; do
      kill $pid 2>/dev/null || true
    done < /tmp/background_pids
    rm -f /tmp/background_pids
  fi
}
trap cleanup EXIT
