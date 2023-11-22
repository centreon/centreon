#!/bin/bash

manageUsersAndGroups() {
  usermod -a -G centreon,nagios,centreon-broker centreon-engine
  usermod -a -G centreon,nagios centreon-broker
  usermod -a -G centreon-engine nagios
  usermod -a -G centreon-engine,centreon-broker centreon
  usermod -a -G centreon-engine centreon-gorgone
  usermod -a -G centreon-broker centreon-gorgone
}

updateEngineBrokerConfigurationRights() {
  if [ "$1" = "rpm" ]; then
    if [ -d /etc/centreon-broker ]; then
      chown -R apache:apache /etc/centreon-broker/*
    fi
    if [ -d /etc/centreon-engine ]; then
        chown -R apache:apache /etc/centreon-engine/*
    fi
  else
    if [ -d /etc/centreon-broker ]; then
      chown -R www-data:www-data /etc/centreon-broker/*
    fi
    if [ -d /etc/centreon-engine ]; then
        chown -R www-data:www-data /etc/centreon-engine/*
    fi
  fi
}

updateSnmpConfiguration() {
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
    manageUsersAndGroups
    updateEngineBrokerConfigurationRights $package_type
    updateSnmpConfiguration
    ;;
  "2" | "upgrade")
    manageUsersAndGroups
    updateEngineBrokerConfigurationRights $package_type
    ;;
  *)
    # $1 == version being installed
    manageUsersAndGroups
    ;;
esac
