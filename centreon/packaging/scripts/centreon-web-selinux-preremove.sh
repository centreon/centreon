#!/bin/bash

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-web selinux rules ..."
  setsebool -P httpd_unified off || :
  setsebool -P httpd_can_network_connect off || :
  setsebool -P httpd_can_network_relay off || :
  setsebool -P httpd_mod_auth_pam off || :
  semodule -r centreon-web || :
fi
