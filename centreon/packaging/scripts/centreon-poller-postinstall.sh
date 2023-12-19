#!/bin/bash

manageUsersAndGroups() {
  echo "Managing users and groups for centreon ..."
  if [ "$1" = "rpm" ]; then
    usermod centreon-engine -a -G centreon,nagios,centreon-broker
    usermod centreon-broker -a -G centreon,nagios
    usermod nagios -a -G centreon-engine
    usermod centreon -a -G centreon-engine,centreon-broker
    usermod centreon-gorgone -a -G centreon-engine
    usermod centreon-gorgone -a -G centreon-broker
  else
    usermod centreon-engine -a -G centreon,centreon-broker
    usermod centreon-broker -a -G centreon
    usermod centreon -a -G centreon-engine,centreon-broker
    usermod centreon-gorgone -a -G centreon-engine
    usermod centreon-gorgone -a -G centreon-broker
  fi
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
    updateEngineBrokerConfigurationRights
    updateSnmpConfiguration
    ;;
  "2" | "upgrade")
    manageUsersAndGroups $package_type
    updateEngineBrokerConfigurationRights
    ;;
  *)
    # $1 == version being installed
    manageUsersAndGroups $package_type
    ;;
esac
