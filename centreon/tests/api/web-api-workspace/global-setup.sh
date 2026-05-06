#!/bin/bash
set -e

COMPOSE="docker compose -f $(dirname $0)/../../../../.github/docker/docker-compose.yml"

# Import dataset via CLAPI for tests
echo "Import dataset via CLAPI for tests"
$COMPOSE cp $(dirname $0)/fixtures web:/tmp
$COMPOSE exec web bash -ex -c "/tmp/fixtures/clapi-import-dataset.sh"

# Import images
echo "Import images"
$COMPOSE exec web bash -ex -c "/tmp/fixtures/sql-import-images.sh"

# Start cron daemon
echo "Starting crond"
$COMPOSE exec web systemctl start crond
