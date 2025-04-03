#!/bin/sh

# Wait for the database to be up and running.
while true ; do
  timeout 20 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" -e 'SELECT User FROM user' mysql
  retval=$?
  if [ "$retval" = 0 ] ; then
    echo 'DB server is running.'
    break ;
  else
    echo 'DB server is not yet responding.'
    sleep 1
  fi
done
