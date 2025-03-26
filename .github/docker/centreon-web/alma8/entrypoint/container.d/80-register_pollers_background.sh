#!/bin/sh

set +x

POLL_INTERVAL=10
MYSQL_TIMEOUT=5
RESTART_WAIT=60

while true ; do
  sleep "$POLL_INTERVAL"
  SQL_RESULT=$(timeout "$MYSQL_TIMEOUT" mysql -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "SELECT id FROM nagios_server WHERE name NOT IN (SELECT name from centreon_storage.instances)" 2>&1)
  if [ $? -eq 124 ]; then
    echo "MySQL query timed out"
    continue
  fi
  case "$SQL_RESULT" in
    *id*)
      echo "Restarting gorgoned to register new pollers."
      if ! systemctl restart gorgoned; then
        echo "Failed to restart gorgoned"
        continue
      fi
      sleep "$RESTART_WAIT"
      ;;
    *)
      echo "No new pollers to register."
      ;;
  esac
done
