#!/bin/bash

# Prepare php upgrade from 8.0
if systemctl --all --type service | grep -q "php8.0-fpm" ; then
  echo "Disabling and stopping php8.0-fpm to migrate to php8.2-fpm ..."
  a2dismod php8.0 > /dev/null 2>&1 || :
  systemctl disable php8.0-fpm > /dev/null 2>&1 || :
  systemctl stop php8.0-fpm > /dev/null 2>&1 || :
fi

# Prepare php upgrade from 8.1
if systemctl --all --type service | grep -q "php8.1-fpm" ; then
  echo "Disabling and stopping php8.1-fpm to migrate to php8.2-fpm ..."
  a2dismod php8.1 > /dev/null 2>&1 || :
  systemctl disable php8.1-fpm > /dev/null 2>&1 || :
  systemctl stop php8.1-fpm > /dev/null 2>&1 || :
fi
