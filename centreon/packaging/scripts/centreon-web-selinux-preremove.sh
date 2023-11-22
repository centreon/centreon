#!/bin/bash

if [ "$1" -lt "1" ]; then
  setsebool -P httpd_unified off > /dev/null 2>&1 || :
  setsebool -P httpd_can_network_connect off > /dev/null 2>&1 || :
  setsebool -P httpd_can_network_relay off > /dev/null 2>&1 || :
  setsebool -P httpd_mod_auth_pam off > /dev/null 2>&1 || :
  semodule -r centreon-web > /dev/null 2>&1 || :
fi
