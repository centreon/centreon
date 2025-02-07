#!/bin/sh

while true ; do
  sleep 10

  SQL_RESULT=$(timeout 5 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "SELECT id FROM nagios_server WHERE name NOT IN (SELECT name from centreon_storage.instances)")
  if [[ "$SQL_RESULT" == *"id"* ]] ; then
    echo "Restarting gorgoned to register new pollers."
    systemctl reload gorgoned
    sleep 60
  else
    echo "No new pollers to register."
  fi
done
