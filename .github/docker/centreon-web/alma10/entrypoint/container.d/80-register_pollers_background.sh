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
      echo "Reloading gorgoned to register new pollers."
      if ! systemctl reload gorgoned; then
        echo "Failed to reload gorgoned"
        continue
      fi
      i=0
      while [ "$i" -lt 30 ]; do
        if curl --max-time 1 --connect-timeout 1 -s http://127.0.0.1:8085/ > /dev/null 2>&1; then
          echo "Gorgone is ready."
          break
        fi
        sleep 1
        i=$((i + 1))
      done
      if [ "$i" -eq 30 ]; then
        echo "Warning: Gorgone did not become ready within 30 seconds."
      fi
      sleep "$RESTART_WAIT"
      ;;
  esac
done
