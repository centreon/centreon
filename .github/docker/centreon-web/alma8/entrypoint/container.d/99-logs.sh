#!/bin/sh

touch /tmp/docker.ready
echo "Centreon is ready"

tail -f \
  /var/log/httpd/error_log \
  /var/log/php-fpm/centreon-error.log \
  /var/log/php-fpm/error.log \
  /var/log/httpd/access_log
