#!/bin/sh

#set -e
set -x

# Run each startup script located in BASEDIR.
# ls is required to ensure that the scripts are properly sorted by name.
BASEDIR="/usr/share/centreon/container.d"
for file in `ls $BASEDIR` ; do
  . "$BASEDIR/$file"
done

# PUT HERE: Commands that will register Central server to Vault
# Should be conditionned to Web being up-and-running (health check to container)