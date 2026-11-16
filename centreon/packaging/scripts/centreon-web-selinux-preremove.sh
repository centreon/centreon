#!/bin/bash

PHP_FPM_PORT=9042

if [ "$1" -lt "1" ]; then
  echo "Removing centreon-web selinux rules ..."
  setsebool -P httpd_unified off || :
  setsebool -P httpd_can_network_connect off || :
  setsebool -P httpd_can_network_relay off || :
  setsebool -P httpd_mod_auth_pam off || :
  # the port mapping references a type of the module: delete it first
  if semanage port -l -C -n | awk -v p="$PHP_FPM_PORT" '$1 == "centreon_php_fpm_port_t" && $2 == "tcp" { for (i = 3; i <= NF; i++) { f = $i; sub(/,$/, "", f); if (f == p) found = 1 } } END { exit !found }'; then
    semanage port -d -t centreon_php_fpm_port_t -p tcp "$PHP_FPM_PORT" \
      || echo "ERROR: could not remove the tcp/$PHP_FPM_PORT label, the module removal will fail" >&2
  fi
  semodule -r centreon-web || :
fi
