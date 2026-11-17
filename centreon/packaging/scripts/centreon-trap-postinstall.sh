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

startSnmptrapdAsRoot() {
  # deb only: snmptrapd runs as Debian-snmp, which cannot "su -l centreon" in the traphandle,
  # and is socket-activated, so it only starts on the first trap (el runs it as root at boot)
  local dropin=/etc/systemd/system/snmptrapd.service.d/centreon.conf
  local wants=/etc/systemd/system/multi-user.target.wants/snmptrapd.service
  local marker="# centreon-trap: added the multi-user.target wants link"
  # the marker tells postremove the wants link is ours; one made by an admin is left alone
  local ours=""
  if grep -qxF "$marker" "$dropin" 2>/dev/null || { [ ! -e "$wants" ] && [ ! -L "$wants" ]; }; then
    ours=1
  fi
  mkdir -p /etc/systemd/system/snmptrapd.service.d
  cat <<EOF > "$dropin"
[Service]
User=root
EOF
  [ -n "$ours" ] && echo "$marker" >> "$dropin"
  systemctl daemon-reload ||:
  systemctl add-wants multi-user.target snmptrapd.service ||:
  systemctl restart snmptrapd.service ||:
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
    if [ "$1" = "configure" ]; then
      startSnmptrapdAsRoot
    fi
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
