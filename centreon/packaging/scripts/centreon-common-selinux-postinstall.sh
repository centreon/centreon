#!/bin/bash

install() {
  echo "Installing centreon-common selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-common.pp || :
  restorecon -R -v /run/dbus/system_bus_socket || :
  setsebool -P daemons_enable_cluster_mode on || :
  setsebool -P cluster_can_network_connect on || :
  setsebool -P cluster_manage_all_files on || :
}

upgrade() {
  echo "Updating centreon-common selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-common.pp || :
  restorecon -R -v /run/dbus/system_bus_socket || :
  setsebool -P daemons_enable_cluster_mode on || :
  setsebool -P cluster_can_network_connect on || :
  setsebool -P cluster_manage_all_files on || :
}

action="$1"
if  [ "$1" = "configure" ] && [ -z "$2" ]; then
  action="install"
elif [ "$1" = "configure" ] && [ -n "$2" ]; then
  action="upgrade"
fi

case "$action" in
  "1" | "install")
    install
    ;;
  "2" | "upgrade")
    upgrade
    ;;
esac
