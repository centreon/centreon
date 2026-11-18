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

# Provision a disposable fake module so the legacy module-management API (Module_API collection) has a target
echo "Provision fake module for API tests"
$COMPOSE exec web bash -ex -c "/tmp/fixtures/setup-test-module.sh"

# Start cron daemon (service name differs by distribution: crond on RHEL/Alma, cron on Debian/Trixie)
echo "Starting cron daemon"
$COMPOSE exec web bash -c '
if systemctl start crond 2>/dev/null; then
  echo "Started crond.service"
else
  echo "crond.service not available, falling back to cron.service"
  systemctl start cron
fi
'
