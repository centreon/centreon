#!/bin/sh
touch /tmp/docker.ready
echo "Centreon Trap is ready"

#centreontrapd logs directly to stdout
exec "$@"
