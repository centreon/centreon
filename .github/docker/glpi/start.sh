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

service cron start

/usr/sbin/apache2ctl -D FOREGROUND
