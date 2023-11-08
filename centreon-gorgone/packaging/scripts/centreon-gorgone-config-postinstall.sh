#!/bin/bash

if getent passwd centreon &>/dev/null; then
  /usr/sbin/usermod -a -G centreon-gorgone centreon 2> /dev/null
fi

if getent passwd centreon-engine &>/dev/null; then
  /usr/sbin/usermod -a -G centreon-gorgone centreon-engine 2> /dev/null
fi

if getent passwd centreon-broker &>/dev/null; then
  /usr/sbin/usermod -a -G centreon-gorgone centreon-broker 2> /dev/null
fi

if getent passwd centreon-gorgone &>/dev/null; then
  /usr/sbin/usermod -a -G centreon centreon-gorgone 2> /dev/null
fi

if [ ! -d /var/lib/centreon-gorgone/.ssh ] && [ -d /var/spool/centreon/.ssh ]; then
  cp -r /var/spool/centreon/.ssh /var/lib/centreon-gorgone/.ssh
  chown -R centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone/.ssh
  chmod 600 /var/lib/centreon-gorgone/.ssh/id_rsa
fi
