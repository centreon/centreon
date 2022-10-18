#!/bin/sh

# Start Centreon Engine daemon.
while [ -s /var/log/centreon-engine/centengine.log ]; do
    /etc/init.d/centengine restart
    sleep 1
    if [ -s /var/log/centreon-engine/centengine.log ]; then
        break ;
    fi
    echo "centreon-engine log file is still empty"
done
