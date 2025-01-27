#!/bin/sh

sleep 10
while true ; do
  for var in $(printenv | grep -Eo '^CENTREON_POLLER_[0-9]+='); do
    var_name=$(echo $var | sed 's/=//')
    printenv $var_name

    timeout 5 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "SELECT id FROM nagios_server WHERE name = '$(printenv $var_name)' AND last_restart = 0"
    retval=$?
    if [ "$retval" = 0 ] ; then
      echo "Poller $(printenv $var_name) has never been started. Restarting gorgoned to register it."
      systemctl restart gorgoned
      sleep 60
    fi
  done
done
