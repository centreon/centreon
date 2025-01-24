#!/bin/sh

for var in $(printenv | grep -Eo '^CENTREON_POLLER_[0-9]+='); do
  var_name=$(echo $var | sed 's/=//')
  printenv $var_name

  # while true ; do
  #   timeout 20 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT id FROM platform_topology WHERE hostname = '$(printenv $var_name)'" mysql
  #   retval=$?
  #   if [ "$retval" = 0 ] ; then
  #     echo 'DB server is running.'
  #     break ;
  #   else
  #     echo 'DB server is not yet responding.'
  #     sleep 1
  #   fi
  # done
done
