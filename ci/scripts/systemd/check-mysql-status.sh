#!/bin/bash

# Wait for the database to be up and running.
for i in {1..10} ; do
  timeout 10 mysql -e 'SELECT 1'
  retval=$?

  [ "$retval" = 0 ] && exit 0

  sleep 1
done

exit 2
