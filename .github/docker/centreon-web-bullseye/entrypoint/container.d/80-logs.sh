# Print logs.

tail -f \
  /var/log/apache2/error.log \
  /var/log/php8.1-fpm-centreon-error.log \
  /var/log/apache2/access.log
