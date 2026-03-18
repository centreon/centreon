#!/bin/bash

# This check some specific configuration case we now we can't handle automatically in postinstall to fail the install and force the user to manually update the configuration.
# The final objective is to migrate gorgone pullwss/websocket configuration to apache proxying for simplier tls configuration.
is_gorgone_pullwss_tls=0
is_apache_tls=0
gorgone_file="/etc/centreon-gorgone/config.d/40-gorgoned.yaml"
if [ -f $gorgone_file ] && grep -q "name: proxy" $gorgone_file ; then
  echo "Checking if gorgone is installed and listen for pullwss connection"
  gorgone_conf=$(sed -n '/    httpserver:/,/  -/p' $gorgone_file | tr -s ' ')
  gorgone_conf=$(grep -A 8 "      httpserver:" $gorgone_file | tr -s ' ')
  if echo "$gorgone_conf" | grep -q "ssl: true" && echo "$gorgone_conf" | grep -q "enable: true" ; then
      echo "gorgone listen in ssl mode."
    is_gorgone_pullwss_tls=1;
  fi
fi
apache_dir="/etc/apache2/sites-enabled/"
if [ -d $apache_dir ] && grep -qr "<VirtualHost *:443>" $apache_dir ; then
  echo "apache listen on port 443, so it should be configured for tls."
  is_apache_tls=1;
fi
if [[ $is_gorgone_pullwss_tls == 1 && $is_apache_tls == 0 ]]; then
  echo "Your gorgone configuration is using pullwss with tls enabled but your apache configuration doesn't seems to be configured for tls."
  echo "As this new version need to proxy gorgone behind apache, we can't safely migrate your configuration."
  echo "Please update your apache configuration to use tls before installing centreon-web package or update gorgone configuration to disable tls for pullwss connection if you don't want to use tls for pullwss connection."
  exit 1
fi


# Prepare php upgrade from 8.0
if systemctl --all --type service | grep -q "php8.0-fpm" ; then
  echo "Disabling and stopping php8.0-fpm to migrate to php@PHP_MIN_VERSION@-fpm ..."
  a2dismod php8.0 > /dev/null 2>&1 || :
  systemctl disable php8.0-fpm > /dev/null 2>&1 || :
  systemctl stop php8.0-fpm > /dev/null 2>&1 || :
fi

# Prepare php upgrade from 8.1
if systemctl --all --type service | grep -q "php8.1-fpm" ; then
  echo "Disabling and stopping php8.1-fpm to migrate to php@PHP_MIN_VERSION@-fpm ..."
  a2dismod php8.1 > /dev/null 2>&1 || :
  systemctl disable php8.1-fpm > /dev/null 2>&1 || :
  systemctl stop php8.1-fpm > /dev/null 2>&1 || :
fi

# Prepare php upgrade from 8.2
if systemctl --all --type service | grep -q "php8.2-fpm" ; then
  echo "Disabling and stopping php8.2-fpm to migrate to php@PHP_MIN_VERSION@-fpm ..."
  a2dismod php8.2 > /dev/null 2>&1 || :
  systemctl disable php8.2-fpm > /dev/null 2>&1 || :
  systemctl stop php8.2-fpm > /dev/null 2>&1 || :
fi
