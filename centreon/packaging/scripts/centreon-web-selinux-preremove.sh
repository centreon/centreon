#!/bin/bash

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-web selinux rules ..."
  setsebool -P httpd_unified off || :
  setsebool -P httpd_can_network_connect off || :
  setsebool -P httpd_can_network_relay off || :
  setsebool -P httpd_mod_auth_pam off || :
  # every local port mapping of the module's type blocks its removal: delete them first
  for port in $(semanage port -l -C -n | awk '$1 == "centreon_php_fpm_port_t" && $2 == "tcp" { for (i = 3; i <= NF; i++) { f = $i; sub(/,$/, "", f); print f } }'); do
    semanage port -d -t centreon_php_fpm_port_t -p tcp "$port" \
      || echo "ERROR: could not remove the tcp/$port label, the module removal will fail" >&2
  done
  semodule -r centreon-web || echo "ERROR: could not remove the centreon-web SELinux module" >&2
fi
