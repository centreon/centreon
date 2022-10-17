#!/bin/sh

# Copy configuration files
rm -rf /etc/centreon-engine/*
cp -R /var/cache/centreon/config/engine/1/* /etc/centreon-engine/
chown -R apache. /etc/centreon-engine/*
chmod -R 664 /etc/centreon-engine/*

# Start Centreon Engine daemon.
service centengine start
service centengine reload
