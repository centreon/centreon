#!/bin/sh

touch /tmp/docker.ready
echo "Centreon is ready"

tail -f \
  /var/log/httpd/error_log \
  /var/log/centreon/prod.web.log
