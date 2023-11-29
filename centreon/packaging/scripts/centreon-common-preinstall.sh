#!/bin/bash

echo "Removing previous centreon engine and broker configuration files in cache ..."
rm -rf /var/cache/centreon/config/engine/* 2> /dev/null
rm -rf /var/cache/centreon/config/broker/* 2> /dev/null
rm -rf /var/cache/centreon/config/export/* 2> /dev/null

echo "Creating centreon user and group ..."
getent group centreon &>/dev/null || groupadd -r centreon
getent passwd centreon &>/dev/null || useradd -g centreon -m -d /var/spool/centreon -r centreon 2> /dev/null

if getent passwd centreon-broker > /dev/null 2>&1; then
  usermod -a -G centreon-broker centreon
  usermod -a -G centreon centreon-broker
fi

if getent passwd centreon-engine > /dev/null 2>&1; then
  usermod -a -G centreon-engine centreon
  usermod -a -G centreon centreon-engine
fi
