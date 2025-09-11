#!/bin/sh
chown -R centreon:centreon /var/lib/centreon/centcore /var/lib/centreon/
chown www-data:www-data /var/cache/centreon/symfony 

chmod -R 775 /var/lib/centreon/centcore /var/lib/centreon/
# chmod 644 /etc/centreon/config.d/10-database.yaml
#systemctl start apache2
