#!/bin/sh
# Reload centreontrapd via SIGHUP when centreontrapd.sdb is updated.
# Handles both in-place writes (close_write) and atomic replacements (moved_to/create).

SDB="/etc/snmp/centreon_traps/centreontrapd.sdb"
SDB_DIR=$(dirname "$SDB")
SDB_FILE=$(basename "$SDB")

until pgrep -f centreontrapd > /dev/null 2>&1; do
  sleep 1
done

echo "sdb-watch: monitoring ${SDB} for updates"

inotifywait -m -q -e close_write,moved_to,create --format '%f' "${SDB_DIR}" | \
while IFS= read -r filename; do
  [ "$filename" = "$SDB_FILE" ] || continue
  pid=$(pgrep -f centreontrapd | head -1)
  if [ -n "$pid" ]; then
    echo "sdb-watch: ${SDB_FILE} updated — sending SIGHUP to centreontrapd (PID: ${pid})"
    kill -HUP "$pid"
  fi
done
