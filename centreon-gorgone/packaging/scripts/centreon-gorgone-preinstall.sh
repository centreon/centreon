#!/bin/bash

if ! getent group centreon-gorgone > /dev/null 2>&1; then
  groupadd -r centreon-gorgone
fi

# Check if the centreon-gorgone user exists, and create it if not
if ! getent passwd centreon-gorgone > /dev/null 2>&1; then
  useradd -g centreon-gorgone -m -d /var/lib/centreon-gorgone -r centreon-gorgone 2> /dev/null
fi
