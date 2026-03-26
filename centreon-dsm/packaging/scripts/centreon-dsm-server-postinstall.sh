#!/bin/bash

restartDsmd() {
  systemctl daemon-reload ||:
  systemctl unmask dsmd.service ||:
  systemctl preset dsmd.service ||:
  systemctl enable dsmd.service ||:
  systemctl restart dsmd.service ||:
}

fixPurgePermissions() {
  # Update log file permissions which has been potentially created by centreon user
  LOG_FILE="/var/log/centreon/centreon_dsm_purge.log"
  if [ -f "$LOG_FILE" ]; then
    if [ "$1" = "rpm" ]; then
      chown apache:apache "$LOG_FILE"
    else
      chown www-data:www-data "$LOG_FILE"
    fi
  fi
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
    restartDsmd
    ;;
  "2" | "upgrade")
    fixPurgePermissions $package_type
    restartDsmd
    ;;
  *)
    restartDsmd
    ;;
esac
