#!/bin/sh

# Delete ib_logfiles to avoid read/rebuild time on startup (~9s)
# rm -f /var/lib/mysql/ib_logfile*

# # Start database server.
# service mysql start

# # Wait for the database to be up and running.
# while true ; do
#   timeout 10 mysql -e 'SELECT id FROM nagios_server' centreon
#   retval=$?
#   if [ "$retval" = 0 ] ; then
#     break ;
#   else
#     echo 'DB server is not yet responding.'
#     sleep 1
#   fi
# done

mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} < /usr/local/src/centreon.sql
mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} < /usr/local/src/centreon_storage.sql

sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm
mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'host'"

#mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"
