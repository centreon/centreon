#!/bin/sh

# Copy configuration files
rm -rf /etc/centreon-broker/*
cp -R /var/cache/centreon/config/broker/1/* /etc/centreon-broker/
chown -R apache. /etc/centreon-broker/*
chmod -R 664 /etc/centreon-broker/*

# Start Centreon Broker daemons.
/etc/init.d/cbd start
