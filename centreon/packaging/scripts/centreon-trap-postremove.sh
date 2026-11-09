#!/bin/bash

# deb purge only (rpm passes a number): drop the snmptrapd root override created at install
if [ "$1" = "purge" ]; then
  rm -f /etc/systemd/system/snmptrapd.service.d/centreon.conf
  rmdir /etc/systemd/system/snmptrapd.service.d 2>/dev/null ||:
  rm -f /etc/systemd/system/multi-user.target.wants/snmptrapd.service
  systemctl daemon-reload ||:
  systemctl try-restart snmptrapd.service ||:
fi
