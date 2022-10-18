#!/bin/sh



# Start Centreon Engine daemon.
while [ `cat /var/log/centreon-engine/centengine.log | grep "initialized successfully" | wc -l` -lt 1 ]; do
    echo "centreon-engine log file is empty, restarting centengine"
    /etc/init.d/centengine restart
    sleep 0.5
    /etc/init.d/centengine reload
    sleep 0.5
done
