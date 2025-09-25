#!/bin/sh
chown -R centreon:centreon /var/lib/centreon/centcore /var/lib/centreon/
chown www-data:www-data /var/cache/centreon/symfony chown -R www-data:www-data /var/cache/centreon/config
chown -R www-data:www-data /var/cache/centreon/config

chmod -R 775 /var/lib/centreon/centcore /var/lib/centreon/

su - www-data -s /bin/bash -c "php /usr/share/centreon/bin/console cache:clear"
# chmod 644 /etc/centreon/config.d/10-database.yaml
#systemctl start apache2
