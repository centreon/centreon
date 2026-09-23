#!/bin/sh
touch /tmp/docker.ready
echo "[SNMPTRAPD] Centreon snmptrapd is ready"
echo "[SNMPTRAPD] Listening on UDP/162"
echo "[SNMPTRAPD] Spool directory: /var/spool/centreontrapd"

exec "$@"
