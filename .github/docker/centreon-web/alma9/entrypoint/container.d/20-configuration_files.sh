#!/bin/sh

# Copy engine configuration files
rm -rf /etc/centreon-engine/*
cp -R /var/cache/centreon/config/engine/1/* /etc/centreon-engine/
chown -R apache. /etc/centreon-engine/*
chmod -R 664 /etc/centreon-engine/*

# Copy broker configuration files
rm -rf /etc/centreon-broker/*
cp -R /var/cache/centreon/config/broker/1/* /etc/centreon-broker/
chown -R apache. /etc/centreon-broker/*
chmod -R 664 /etc/centreon-broker/*

su - apache -s /bin/bash -c "centreon -d -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1"
su - apache -s /bin/bash -c "centreon -d -u admin -p Centreon\!2021 -a POLLERTEST -v 1"
su - apache -s /bin/bash -c "centreon -d -u admin -p Centreon\!2021 -a POLLERMOVE -v 1"
