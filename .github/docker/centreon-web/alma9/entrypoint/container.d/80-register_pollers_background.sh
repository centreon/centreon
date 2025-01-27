#!/bin/sh

while true ; do
  sleep 10
  for var in $(printenv | grep -Eo '^CENTREON_POLLER_[0-9]+='); do
    var_name=$(echo $var | sed 's/=//')
    printenv $var_name

    SQL_RESULT=$(timeout 5 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon_storage -e "SELECT instance_id FROM instances WHERE name = '$(printenv $var_name)'")
    if [[ "$SQL_RESULT" != *"instance_id"* ]] ; then
      echo "Poller $(printenv $var_name) is not in monitoring database. Restarting gorgoned to register it."
      systemctl restart gorgoned
      sleep 60
    fi
  done
done
