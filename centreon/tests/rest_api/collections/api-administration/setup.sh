#!/bin/bash
docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml exec web mkdir -p /usr/share/centreon/www/img/media/test

sed -i 's@"vault": 0@"vault": 3@' /usr/share/centreon/config/features.json
