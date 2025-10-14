#!/bin/sh
mkdir -p /var/log/centreon/ /var/lib/centreon/centcore
touch /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log

chown -R centreon:centreon /var/lib/centreon/centcore /var/lib/centreon/ /var/log/centreon/ /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log /etc/centreon
chown www-data:www-data /var/cache/centreon/symfony 
chown -R www-data:www-data /var/cache/centreon/

chmod -R 775 /var/lib/centreon/centcore /var/lib/centreon/ /var/cache/centreon/
chmod 1230 /var/log/centreon/centreon-web.log

su - www-data -s /bin/bash -c "php /usr/share/centreon/bin/console cache:clear"
