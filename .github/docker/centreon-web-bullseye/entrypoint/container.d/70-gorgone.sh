#!/bin/sh

# Run gorgone in background.
su - centreon-gorgone -c "/usr/bin/perl /usr/bin/gorgoned --config=/etc/centreon-gorgone/config.yaml --logfile=/var/log/centreon-gorgone/gorgoned.log --severity=info" &
