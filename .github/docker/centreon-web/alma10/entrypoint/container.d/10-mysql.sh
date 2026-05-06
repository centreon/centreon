#!/bin/sh

mkdir -p /etc/my.cnf.d
printf '[client]\nskip-ssl\n' > /etc/my.cnf.d/client-no-ssl.cnf

# Wait for the database to be up and running.
echo "Waiting for DB server to be ready..."
while true ; do
  if mysqladmin -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" ping --connect-timeout=5 ; then
    echo "DB server is running."
    break
  fi
  sleep 1
done
