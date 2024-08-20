#!/bin/bash

fixConfigurationFileRights() {
  # force update of configuration file rights since they are not updated automatically by nfpm
  chmod 0640 /etc/centreon-gorgone/config.d/30-centreon.yaml
  chmod 0640 /etc/centreon-gorgone/config.d/31-centreon-api.yaml
  chmod 0640 /etc/centreon-gorgone/config.d/50-centreon-audit.yaml
  chmod 0770 /etc/centreon-gorgone/config.d
  chmod 0770 /etc/centreon-gorgone/config.d/cron.d
}

manageUserGroups() {
  if getent passwd centreon  > /dev/null 2>&1; then
    usermod -a -G centreon-gorgone centreon 2> /dev/null
  fi

  if getent passwd centreon-engine > /dev/null 2>&1; then
    usermod -a -G centreon-gorgone centreon-engine 2> /dev/null
  fi

  if getent passwd centreon-broker > /dev/null 2>&1; then
    usermod -a -G centreon-gorgone centreon-broker 2> /dev/null
  fi

  if getent passwd centreon-gorgone > /dev/null 2>&1; then
    usermod -a -G centreon centreon-gorgone 2> /dev/null
  fi
}

addGorgoneSshKeys() {
  if [ ! -d /var/lib/centreon-gorgone/.ssh ] && [ -d /var/spool/centreon/.ssh ]; then
    cp -r /var/spool/centreon/.ssh /var/lib/centreon-gorgone/.ssh
    chown -R centreon-gorgone:centreon-gorgone /var/lib/centreon-gorgone/.ssh
    chmod 600 /var/lib/centreon-gorgone/.ssh/id_rsa
  fi
}

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
    manageUserGroups
    addGorgoneSshKeys
    ;;
  "2" | "upgrade")
    manageUserGroups
    fixConfigurationFileRights
    addGorgoneSshKeys
    ;;
  *)
    # $1 == version being installed
    manageUserGroups
    addGorgoneSshKeys
    ;;
esac
