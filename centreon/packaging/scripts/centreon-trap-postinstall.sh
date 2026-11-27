#!/bin/bash

# tells postremove that the snmptrapd wants link is ours; one made by an admin is left alone
WANTS_MARKER="# centreon-trap: added the multi-user.target wants link"

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

useSudoTraphandle() {
  # deb only: snmptrapd runs as Debian-snmp, which cannot "su -l centreon";
  # /etc/sudoers.d/centreon-trap lets it run the forwarder as centreon instead
  [ -f /etc/snmp/snmptrapd.conf ] || return 0
  sed -i -e 's#^traphandle default su -l centreon -c "/usr/share/centreon/bin/centreontrapdforward"$#traphandle default /usr/bin/sudo -n -u centreon /usr/share/centreon/bin/centreontrapdforward#' \
    /etc/snmp/snmptrapd.conf
  if grep -qE '^traphandle .*su .*centreontrapdforward' /etc/snmp/snmptrapd.conf; then
    echo "WARNING: /etc/snmp/snmptrapd.conf was edited and still uses su, which fails for Debian-snmp:" \
      "use 'traphandle default /usr/bin/sudo -n -u centreon /usr/share/centreon/bin/centreontrapdforward'"
  fi
}

dropRootOverride() {
  # deb only: earlier builds ran snmptrapd as root through this drop-in, the sudo traphandle replaces it;
  # only the exact content they wrote is changed, the marker line is kept for postremove
  local dropin=/etc/systemd/system/snmptrapd.service.d/centreon.conf
  [ -f "$dropin" ] || return 0
  [ "$(grep -vxF "$WANTS_MARKER" "$dropin")" = $'[Service]\nUser=root' ] || return 0
  if grep -qxF "$WANTS_MARKER" "$dropin"; then
    echo "$WANTS_MARKER" > "$dropin"
  else
    rm -f "$dropin"
    rmdir /etc/systemd/system/snmptrapd.service.d 2>/dev/null ||:
  fi
}

startSnmptrapdAtBoot() {
  # deb only: snmptrapd is socket-activated, so it only starts on the first trap (el starts it at boot)
  local wants=/etc/systemd/system/multi-user.target.wants/snmptrapd.service
  if [ ! -e "$wants" ] && [ ! -L "$wants" ]; then
    mkdir -p /etc/systemd/system/snmptrapd.service.d
    echo "$WANTS_MARKER" > /etc/systemd/system/snmptrapd.service.d/centreon.conf
    systemctl add-wants multi-user.target snmptrapd.service ||:
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
    if [ "$1" = "configure" ]; then
      useSudoTraphandle
      dropRootOverride
      startSnmptrapdAtBoot
      systemctl daemon-reload ||:
      systemctl restart snmptrapd.service ||:
    fi
    startCentreonTrap
    ;;
  "2" | "upgrade")
    updateConfiguration
    if [ "$1" = "configure" ]; then
      useSudoTraphandle
      dropRootOverride
      systemctl daemon-reload ||:
      systemctl try-restart snmptrapd.service ||:
    fi
    startCentreonTrap
    ;;
  *)
    # $1 == version being installed
    updateConfiguration
    startCentreonTrap
    ;;
esac
