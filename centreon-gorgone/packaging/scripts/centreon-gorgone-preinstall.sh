#!/bin/bash

if ! /usr/bin/getent group centreon-gorgone &>/dev/null; then
  /usr/sbin/groupadd -r centreon-gorgone
fi

# Check if the centreon-gorgone user exists, and create it if not
if ! /usr/bin/getent passwd centreon-gorgone &>/dev/null; then
  /usr/sbin/useradd -g centreon-gorgone -m -d /var/lib/centreon-gorgone -r centreon-gorgone 2> /dev/null
fi
