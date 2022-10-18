#!/bin/sh

# Start Centreon Engine daemon.
while [ `cat /var/log/centreon-engine/centengine.log | wc -l` -lt 1 ]; do
    echo "centreon-engine log file is empty, restarting centengine"
    /etc/init.d/centengine restart
    sleep 1
done
