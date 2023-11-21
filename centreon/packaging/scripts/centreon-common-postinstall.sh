#!/bin/bash

startCentreon() {
  systemctl daemon-reload ||:
  systemctl unmask centreon.service ||:
  systemctl preset centreon.service ||:
  systemctl enable centreon.service ||:
  systemctl restart centreon.service ||:
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
    startCentreon
    ;;
  "2" | "upgrade")
    startCentreon
    ;;
  *)
    # $1 == version being installed
    startCentreon
    ;;
esac
