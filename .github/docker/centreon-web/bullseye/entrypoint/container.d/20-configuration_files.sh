#!/bin/sh

# Copy engine configuration files
rm -rf /etc/centreon-engine/*
cp -R /var/cache/centreon/config/engine/1/* /etc/centreon-engine/
chown -R www-data. /etc/centreon-engine/*
chmod -R 664 /etc/centreon-engine/*

# Copy broker configuration files
rm -rf /etc/centreon-broker/*
cp -R /var/cache/centreon/config/broker/1/* /etc/centreon-broker/
chown -R www-data. /etc/centreon-broker/*
chmod -R 664 /etc/centreon-broker/*
