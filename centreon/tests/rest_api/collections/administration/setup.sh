#!/bin/bash
docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml exec web mkdir -p /usr/share/centreon/www/img/media/test