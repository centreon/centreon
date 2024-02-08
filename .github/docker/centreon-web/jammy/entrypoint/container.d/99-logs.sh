# Print logs.

echo "Centreon is ready"

tail -f \
  /var/log/apache2/error.log \
  /var/log/php8.1-fpm-centreon-error.log \
  /var/log/apache2/other_vhosts_access.log
