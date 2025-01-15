#!/bin/sh

while true ; do
  timeout 20 mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" -e 'SELECT User FROM user' mysql
  retval=$?
  if [ "$retval" = 0 ] ; then
    echo 'DB server is running.'
    break ;
  else
    echo 'DB server is not yet responding.'
    sleep 1
  fi
done

su - www-data -s /bin/bash -c "php /var/www/html/glpi/bin/console database:install --db-host=${MYSQL_HOST} --db-name=glpi --db-user=root --db-password=${MYSQL_ROOT_PASSWORD} --no-interaction"

mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" glpi <<EOF
  UPDATE glpi_configs SET value = '1' WHERE context = 'core' AND name = 'enable_api';
  UPDATE glpi_configs SET value = 'http://glpi/api' WHERE context = 'core' AND name = 'url_base_api';
  UPDATE glpi_apiclients SET name = 'full access', ipv4_range_start = NULL, ipv4_range_end = NULL, ipv6 = NULL, app_token = '${GLPI_APP_TOKEN}', app_token_date = '2024-10-14 12:33:47';
  UPDATE glpi_users SET api_token = '${GLPI_USER_TOKEN}', api_token_date = '2024-10-14 12:33:47' WHERE name = 'glpi';
EOF

service cron start

/usr/sbin/apache2ctl -D FOREGROUND
