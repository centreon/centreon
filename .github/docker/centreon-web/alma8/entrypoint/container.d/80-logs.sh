# Print logs.
if [ -e /opt/rh/httpd24/root/etc/httpd/logs/error_log ] ; then
  tail -f \
  /opt/rh/httpd24/root/etc/httpd/logs/error_log \
  /var/log/php-fpm/centreon-error.log \
  /var/log/php-fpm/error.log \
  /opt/rh/httpd24/root/etc/httpd/logs/access_log
else
  tail -f \
  /var/log/httpd/error_log \
  /var/log/php-fpm/centreon-error.log \
  /var/log/php-fpm/error.log \
  /var/log/httpd/access_log
fi
