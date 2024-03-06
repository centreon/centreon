#!/bin/bash
docker compose -f $(dirname $0)/../../../../../.github/docker/docker-compose.yml cp $(dirname $0)/images/Admin-Potter.png web:/tmp/Admin-Potter.png
