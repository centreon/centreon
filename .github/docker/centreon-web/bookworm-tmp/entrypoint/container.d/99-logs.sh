#!/bin/sh

touch /tmp/docker.ready
echo "Centreon is ready"

exec "$@"

# tail -f \
#   /var/log/apache2/error.log \
#   /var/log/php8.2-fpm-centreon-error.log
