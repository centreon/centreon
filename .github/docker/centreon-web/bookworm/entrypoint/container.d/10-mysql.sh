#!/bin/sh

# Wait for the database to be up and running.
while true ; do
  mysqladmin -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" ping --connect-timeout=5 2>/dev/null
  retval=$?
  if [ "$retval" = 0 ] ; then
    echo 'DB server is running.'
    break ;
  else
    echo 'DB server is not yet responding.'
    sleep 1
  fi
done
