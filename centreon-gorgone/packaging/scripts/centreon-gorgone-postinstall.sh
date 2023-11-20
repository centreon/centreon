#!/bin/bash

installConfigurationFile() {
  if [ ! -f /etc/centreon-gorgone/config.d/31-centreon-api.yaml ] || grep -q "@GORGONE_USER@" "/etc/centreon-gorgone/config.d/31-centreon-api.yaml"; then
    mv /etc/centreon-gorgone/config.d/centreon-api.yaml /etc/centreon-gorgone/config.d/31-centreon-api.yaml
  else
    mv /etc/centreon-gorgone/config.d/centreon-api.yaml /etc/centreon-gorgone/config.d/centreon-api.yaml.new
  fi
}

startGorgoned() {
  systemctl daemon-reload ||:
  systemctl unmask gorgoned.service ||:
  systemctl preset gorgoned.service ||:
  systemctl enable gorgoned.service ||:
  systemctl restart gorgoned.service ||:
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
    installConfigurationFile
    startGorgoned
    ;;
  "2" | "upgrade")
    installConfigurationFile
    startGorgoned
    ;;
  *)
    # $1 == version being installed
    installConfigurationFile
    startGorgoned
    ;;
esac
