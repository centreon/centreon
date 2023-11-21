#!/bin/bash

action="$1"

removeUsersAndGroups() {
  userdel -r centreon 2> /dev/null
  groupdel centreon
  gpasswd --delete centreon centreon-broker > /dev/null 2>&1 || :
  gpasswd --delete centreon-broker centreon > /dev/null 2>&1 || :
  gpasswd --delete centreon centreon-engine > /dev/null 2>&1 || :
  gpasswd --delete centreon-engine centreon > /dev/null 2>&1 || :
}

case "$action" in
  "0" | "remove")
    removeUsersAndGroups
    ;;
  "1" | "upgrade")
    ;;
  "purge")
    removeUsersAndGroups
    ;;
  *)
    ;;
esac
