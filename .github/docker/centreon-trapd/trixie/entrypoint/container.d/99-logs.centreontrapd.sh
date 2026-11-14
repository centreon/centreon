#!/bin/sh
# MON-214281: centreontrapd (mode 1, SQLite) opens this file to start and
# exits if it is missing or empty -- crash-looping on every restart until
# gorgone delivers it (fpm's deliver-traps.php, see
# 13-generate-broker-config_background.sh). Wait for it instead, same
# fixed-interval poll + once-a-minute log as the broker's own wait-config.
SDB="/etc/snmp/centreon_traps/centreontrapd.sdb"
waited=0
until [ -s "$SDB" ]; do
  [ $((waited % 60)) -eq 0 ] && echo "Waiting for $SDB (delivered by gorgone on export)..."
  sleep 2
  waited=$((waited + 2))
done
echo "Trap database delivered."

touch /tmp/docker.ready
echo "Centreon Trap is ready"

#centreontrapd logs directly to stdout
exec "$@"
