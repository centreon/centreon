#!/bin/bash

# deb purge only (rpm passes a number): undo what the install did for snmptrapd,
# an admin's own content in the drop-in is kept
dropin=/etc/systemd/system/snmptrapd.service.d/centreon.conf
marker="# centreon-trap: added the multi-user.target wants link"
if [ "$1" = "purge" ] && [ -f "$dropin" ]; then
  if grep -qxF "$marker" "$dropin"; then
    rm -f /etc/systemd/system/multi-user.target.wants/snmptrapd.service
  fi
  # earlier builds also wrote "[Service] User=root" in it
  rest="$(grep -vxF "$marker" "$dropin")"
  if [ -z "$rest" ] || [ "$rest" = $'[Service]\nUser=root' ]; then
    rm -f "$dropin"
    rmdir /etc/systemd/system/snmptrapd.service.d 2>/dev/null ||:
  else
    printf '%s\n' "$rest" > "$dropin"
  fi
  systemctl daemon-reload ||:
  systemctl try-restart snmptrapd.service ||:
fi
