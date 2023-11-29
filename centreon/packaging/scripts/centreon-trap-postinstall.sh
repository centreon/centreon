#!/bin/bash

updateConfiguration() {
  if [ -f /etc/snmp/snmptrapd.conf ]; then
    echo "Updating snmptrapd configuration to handle trap by centreontrapdforward ..."
    grep disableAuthorization /etc/snmp/snmptrapd.conf &>/dev/null && \
      sed -i -e "s/disableAuthorization .*/disableAuthorization yes/g" /etc/snmp/snmptrapd.conf
    grep disableAuthorization /etc/snmp/snmptrapd.conf &>/dev/null || \
      cat <<EOF >> /etc/snmp/snmptrapd.conf
disableAuthorization yes
EOF
    grep centreontrapdforward /etc/snmp/snmptrapd.conf &>/dev/null ||
      cat <<EOF >> /etc/snmp/snmptrapd.conf
# Centreon custom configuration
traphandle default su -l centreon -c "/usr/share/centreon/bin/centreontrapdforward"
EOF
  fi
}

startCentreonTrap() {
  systemctl daemon-reload ||:
  systemctl unmask centreontrapd.service ||:
  systemctl preset centreontrapd.service ||:
  systemctl enable centreontrapd.service ||:
  systemctl restart centreontrapd.service ||:
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
    updateConfiguration
    startCentreonTrap
    ;;
  "2" | "upgrade")
    updateConfiguration
    startCentreonTrap
    ;;
  *)
    # $1 == version being installed
    updateConfiguration
    startCentreonTrap
    ;;
esac
