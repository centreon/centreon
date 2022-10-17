#!/bin/sh

# Run gorgone in background.
su - centreon-gorgone -c "/usr/bin/perl /usr/bin/gorgoned --config=/etc/centreon-gorgone/config.yaml --logfile=/var/log/centreon-gorgone/gorgoned.log --severity=info" &

# Wait for centreon-engine configuration files to be exported
timeout 10 bash -c -- "while [ ! -f /etc/centreon-engine/tags.cfg ]; do sleep 1; done"
