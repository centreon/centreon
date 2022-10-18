#!/bin/sh

# Run gorgone in background.
su - centreon-gorgone -c "/usr/bin/perl /usr/bin/gorgoned --config=/etc/centreon-gorgone/config.yaml --logfile=/var/log/centreon-gorgone/gorgoned.log --severity=info" &
while [ `cat /var/log/centreon-gorgone/gorgoned.log | grep "Create module 'legacycmd' process" | wc -l` -lt 1 ]; do
    sleep 1
done
