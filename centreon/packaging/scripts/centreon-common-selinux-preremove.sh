#!/bin/bash

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-common selinux rules ..."
  setsebool -P daemons_enable_cluster_mode off > /dev/null 2>&1 || :
  setsebool -P cluster_can_network_connect off > /dev/null 2>&1 || :
  setsebool -P cluster_manage_all_files off > /dev/null 2>&1 || :
  semodule -r centreon-common > /dev/null 2>&1 || :
fi
