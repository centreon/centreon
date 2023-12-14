#!/bin/bash

install() {
  echo "Installing centreon-common selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-common.pp > /dev/null 2>&1 || :
  restorecon -R -v /run/dbus/system_bus_socket > /dev/null 2>&1 || :
  setsebool -P daemons_enable_cluster_mode on > /dev/null 2>&1 || :
  setsebool -P cluster_can_network_connect on > /dev/null 2>&1 || :
  setsebool -P cluster_manage_all_files on > /dev/null 2>&1 || :
}

upgrade() {
  echo "updating centreon-common selinux rules ..."
  semodule -i /usr/share/selinux/packages/centreon/centreon-common.pp > /dev/null 2>&1 || :
  restorecon -R -v /run/dbus/system_bus_socket > /dev/null 2>&1 || :
  setsebool -P daemons_enable_cluster_mode on > /dev/null 2>&1 || :
  setsebool -P cluster_can_network_connect on > /dev/null 2>&1 || :
  setsebool -P cluster_manage_all_files on > /dev/null 2>&1 || :
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
