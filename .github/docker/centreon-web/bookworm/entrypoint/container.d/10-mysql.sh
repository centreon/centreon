#!/bin/sh

# Wait for the database to be up and running.
: "${MYSQL_HOST:=db}"
: "${MYSQL_ROOT_PASSWORD:=centreon}"
: "${DB_TIMEOUT:=300}"

echo "Waiting for database at ${MYSQL_HOST}..."

START_TIME=$(date +%s)
while true; do
  CURRENT_TIME=$(date +%s)
  ELAPSED=$((CURRENT_TIME - START_TIME))

  if [ $ELAPSED -gt $DB_TIMEOUT ]; then
    echo "ERROR: Database connection timeout after ${DB_TIMEOUT} seconds"
    exit 1
  fi

  if timeout 5 mysql -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" -e 'SELECT 1' >/dev/null 2>&1; then
    echo "Database is ready (took ${ELAPSED}s)"
    break
  fi

  echo "Database not ready yet... (${ELAPSED}s/${DB_TIMEOUT}s)"
  sleep 3
done
