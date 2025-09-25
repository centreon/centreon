#!bin/sh

php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERGENERATE -v 1 
php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a CFGMOVE -v 1
php /usr/share/centreon/bin/centreon -u admin -p 'Centreon2025!' -a POLLERRESTART -v 1

chown -R www-data:www-data /var/cache/centreon/config
chown -R www-data:www-data /var/cache/centreon/
su - www-data -s /bin/bash -c "php /usr/share/centreon/bin/console cache:clear"
