#!/bin/bash

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-common selinux rules ..."
  setsebool -P daemons_enable_cluster_mode off || :
  setsebool -P cluster_can_network_connect off || :
  setsebool -P cluster_manage_all_files off || :
  semodule -r centreon-common || :
fi
