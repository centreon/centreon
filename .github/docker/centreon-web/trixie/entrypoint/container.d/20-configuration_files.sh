#!/bin/sh

echo "Generating poller configuration files..."
su www-data -s /bin/bash -c "centreon -u admin -p Centreon\!2021 -a POLLERGENERATE -v 1" 2>&1 | grep -v "^Cannot set configuration file owner"

echo "Copying configuration files to engine/broker directories..."
su www-data -s /bin/bash -c "centreon -u admin -p Centreon\!2021 -a CFGMOVE -v 1"
