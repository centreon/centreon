#!/bin/sh

if [ "$1" = "configure" ] ; then

  if [ "$(getent passwd centreon)" ]; then
    chown -R centreon:centreon /var/log/centreon-ha
    chmod -R 0755 /var/log/centreon-ha
    chmod gu+x /usr/lib/ocf/resource.d/heartbeat/db-centreon
  fi

fi
exit 0
