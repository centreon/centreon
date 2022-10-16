#!/bin/sh

# Start Centreon Engine daemon.
su - centreon-engine -c "/usr/sbin/centengine /etc/centreon-engine/centengine.cfg &"
pid=`su - centreon-engine -c "echo \\$!"`
echo "$pid" > "/var/run/centengine.pid"
