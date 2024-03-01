#!/bin/bash

docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml exec web mkdir -p /usr/share/centreon/www/img/media/test
docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml cp $(dirname $0)/images/centreon.png web:/usr/share/centreon/www/img/media/test/centreon.png
