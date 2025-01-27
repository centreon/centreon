#!/bin/sh

while true ; do
  sleep 30

  for var in $(printenv | grep -Eo '^CENTREON_POLLER_[0-9]+='); do
    var_name=$(echo $var | sed 's/=//')
    poller_name=$(printenv $var_name)
    if getent hosts $poller_name; then
      echo "Poller $poller_name is reachable by central server."
    fi
  done

  SQL_RESULT=$(timeout 5 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "SELECT id FROM nagios_server WHERE name NOT IN (SELECT name from centreon_storage.instances)")
  if [[ "$SQL_RESULT" == *"id"* ]] ; then
    echo "Restarting gorgoned to register new pollers."
    systemctl restart gorgoned
    sleep 120
  else
    echo "No new pollers to register."
  fi
done
