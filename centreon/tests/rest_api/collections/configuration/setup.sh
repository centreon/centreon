#!/bin/bash

if [[ "$1" == *Command* ]]; then
  # Import dataset via CLAPI for tests
  echo "Import dataset via CLAPI for tests"
  docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml cp ./fixtures web:/tmp
  docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml exec web ls -la /tmp/fixtures
  docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml exec web bash -ex -c "/tmp/fixtures/clapi-import-dataset.sh"
fi
