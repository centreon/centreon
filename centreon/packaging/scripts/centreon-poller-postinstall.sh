#!/bin/bash

manageUsersAndGroups() {
  echo "Managing users and groups for centreon ..."
  if ! getent group nagios &>/dev/null; then
    groupadd -r nagios
  fi
  if ! id nagios &>/dev/null; then
    useradd -r -g nagios -s /sbin/nologin nagios
  fi
  usermod centreon-engine -a -G centreon,nagios,centreon-broker
  usermod centreon-broker -a -G centreon,nagios
  usermod nagios -a -G centreon-engine
  usermod centreon -a -G centreon-engine,centreon-broker
  usermod centreon-gorgone -a -G centreon-engine
  usermod centreon-gorgone -a -G centreon-broker
}

fixVarLibCentreonRights() {
  # /var/lib/centreon itself is never declared as an explicit directory entry
  # by any package (only subdirectories like centplugins are), so on a deb
  # install some other package's file list can cause dpkg to auto-vivify it
  # with default root:root ownership, and nothing corrects it afterward.
  # Confirmed reproducible on a clean debian13 central install (rpm is
  # unaffected). This leaves it unwritable by the web process
  # (www-data/apache, via the centreon group), breaking the legacy
  # centcore.cmd config-export mechanism with a "Permission denied" error.
  # Fix it here, unconditionally, since centreon-poller is always the last
  # package in the transaction.
  echo "Fixing rights of /var/lib/centreon ..."
  chown centreon:centreon /var/lib/centreon
  chmod 0775 /var/lib/centreon
}

updateEngineBrokerConfigurationRights() {
  echo "Fixing rights of centreon engine and broker configuration files ..."
  if [ -d /etc/centreon-broker ]; then
    chmod -R g+w /etc/centreon-broker
  fi
  if [ -d /etc/centreon-engine ]; then
    chmod -R g+w /etc/centreon-engine
  fi
}

updateSnmpConfiguration() {
  echo "Updating snmpd configuration to allow OIDs from .1.3.6.1 ..."
  sed -i \
    -e "/^view.*\.1\.3\.6\.1\.2\.1\.1$/i\
view centreon included .1.3.6.1" \
    -e "/^access.*$/i\
access notConfigGroup \"\" any noauth exact centreon none none" \
    /etc/snmp/snmpd.conf
}

fixPluginsPermissions() {
  echo "Updating nagios plugins permissions ..."
  for plugin in /usr/lib64/nagios/plugins/check_icmp /usr/lib64/nagios/plugins/check_dhcp; do
    if [ -f "$plugin" ]; then
      chgrp nagios "$plugin"
      chmod u+s "$plugin"
    fi
  done
}

package_type="rpm"
if  [ "$1" = "configure" ]; then
  package_type="deb"
fi

action="$1"
if  [ "$1" = "configure" ] && [ -z "$2" ]; then
  # Alpine linux does not pass args, and deb passes $1=configure
  action="install"
elif [ "$1" = "configure" ] && [ -n "$2" ]; then
  # deb passes $1=configure $2=<current version>
  action="upgrade"
fi

case "$action" in
  "1" | "install")
    manageUsersAndGroups $package_type
    fixVarLibCentreonRights
    updateEngineBrokerConfigurationRights
    updateSnmpConfiguration
    fixPluginsPermissions
    ;;
  "2" | "upgrade")
    manageUsersAndGroups $package_type
    fixVarLibCentreonRights
    updateEngineBrokerConfigurationRights
    ;;
  *)
    # $1 == version being installed
    manageUsersAndGroups $package_type
    fixVarLibCentreonRights
    ;;
esac
