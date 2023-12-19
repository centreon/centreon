#!/bin/bash

removeUsersAndGroups() {
  echo "Removing centreon user and group ..."
  userdel -r centreon > /dev/null 2>&1 || :
  groupdel centreon > /dev/null 2>&1 || :
  gpasswd --delete centreon centreon-broker > /dev/null 2>&1 || :
  gpasswd --delete centreon-broker centreon > /dev/null 2>&1 || :
  gpasswd --delete centreon centreon-engine > /dev/null 2>&1 || :
  gpasswd --delete centreon-engine centreon > /dev/null 2>&1 || :
}

action="$1"
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
