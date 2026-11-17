#!/bin/bash

# deb purge only (rpm passes a number): drop the snmptrapd root override created at install
dropin=/etc/systemd/system/snmptrapd.service.d/centreon.conf
if [ "$1" = "purge" ] && [ -f "$dropin" ]; then
  if grep -qxF "# centreon-trap: added the multi-user.target wants link" "$dropin"; then
    rm -f /etc/systemd/system/multi-user.target.wants/snmptrapd.service
  fi
  rm -f "$dropin"
  rmdir /etc/systemd/system/snmptrapd.service.d 2>/dev/null ||:
  systemctl daemon-reload ||:
  systemctl try-restart snmptrapd.service ||:
fi
