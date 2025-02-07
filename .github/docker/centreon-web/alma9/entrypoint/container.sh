#!/bin/sh

#set -e
set -x

# Run each startup script located in BASEDIR.
# ls is required to ensure that the scripts are properly sorted by name.
BASEDIR="/usr/share/centreon/container.d"
for file in `ls $BASEDIR` ; do
  if [[ "$file" == *"_background"* ]]; then
    . "$BASEDIR/$file" &
  else
    . "$BASEDIR/$file"
  fi
done
