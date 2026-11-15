#!/bin/bash

. /usr/share/selinux/packages/centreon/centreon-selinux-functions.sh

# booleans are left as they are: the admin may rely on them for something else
if [ "$1" -lt "1" ]; then
  echo "Removing centreon-web selinux rules ..."
  # every local port mapping of the module's type blocks its removal: delete them first
  for port in $(semanage port -l -C -n | awk '$1 == "centreon_php_fpm_port_t" && $2 == "tcp" { for (i = 3; i <= NF; i++) { f = $i; sub(/,$/, "", f); print f } }'); do
    semanage port -d -t centreon_php_fpm_port_t -p tcp "$port" \
      || echo "ERROR: could not remove the tcp/$port label, the module removal will fail" >&2
  done
  centreon_selinux_unload centreon-web
fi
