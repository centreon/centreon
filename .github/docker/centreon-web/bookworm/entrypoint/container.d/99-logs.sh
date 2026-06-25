#!/bin/sh

touch /tmp/docker.ready
echo "Centreon is ready"

tail -f \
  /var/log/apache2/error.log \
  /var/log/centreon/prod.web.log \
  /var/log/apache2/other_vhosts_access.log
