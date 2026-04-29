#!/bin/sh

mkdir -p /etc/mysql/conf.d
printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/client-no-ssl.cnf

# Wait for the database to be up and running.
echo "Waiting for DB server to be ready..."
while true ; do
  if mysqladmin -h"${MYSQL_HOST}" -uroot -p"${MYSQL_ROOT_PASSWORD}" ping --connect-timeout=5 ; then
    echo "DB server is running."
    break
  fi
  sleep 1
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
