#!/bin/sh

# Run gorgone in background.
su - centreon-gorgone -c "/usr/bin/perl /usr/bin/gorgoned --config=/etc/centreon-gorgone/config.yaml --logfile=/var/log/centreon-gorgone/gorgoned.log --severity=debug" &
while [ `cat /var/log/centreon-gorgone/gorgoned.log | grep "Setcoreid changed 1" | wc -l` -lt 1 ]; do
    sleep 1
done
