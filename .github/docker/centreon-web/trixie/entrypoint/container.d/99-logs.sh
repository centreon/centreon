#!/bin/sh

touch /tmp/docker.ready
echo "Centreon is ready"

tail -f \
  /var/log/apache2/error.log \
  /var/log/php${PHP_VERSION}-fpm-centreon-error.log
