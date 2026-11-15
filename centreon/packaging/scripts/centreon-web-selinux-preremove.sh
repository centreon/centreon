#!/bin/bash

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-web selinux rules ..."
  setsebool -P httpd_unified off || :
  setsebool -P httpd_can_network_connect off || :
  setsebool -P httpd_can_network_relay off || :
  setsebool -P httpd_mod_auth_pam off || :
  # the port mapping references a type of the module: delete it first
  semanage port -d -p tcp 9042 > /dev/null 2>&1 || :
  semodule -r centreon-web || :
fi
