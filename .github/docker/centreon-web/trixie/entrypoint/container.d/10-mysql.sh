#!/bin/sh

# Wait for the database to be up and running.
echo "Waiting for DB server to be ready..."
while true ; do
  if mysqladmin -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" ping --connect-timeout=5 2>/dev/null ; then
    echo "DB server is running."
    break
  fi
  sleep 1
done
