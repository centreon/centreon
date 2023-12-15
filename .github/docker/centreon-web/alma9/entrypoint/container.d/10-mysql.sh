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

# mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD}  <<< "SET autocommit=0; source /usr/local/src/centreon.sql; COMMIT;"
# mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD}  <<< "SET autocommit=0; source /usr/local/src/centreon_storage.sql; COMMIT;"

# sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
# sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm
# mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"

# #mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"
# mysql -h${MYSQL_HOST} -uroot -p${MYSQL_ROOT_PASSWORD} -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json
cd /usr/share/centreon/www/install/steps/process
su apache -s /bin/bash -c "php configFileSetup.php"
su apache -s /bin/bash -c "php installConfigurationDb.php"
su apache -s /bin/bash -c "php installStorageDb.php"
su apache -s /bin/bash -c "php createDbUser.php"
su apache -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
su apache -s /bin/bash -c "php partitionTables.php"
su apache -s /bin/bash -c "php generationCache.php"
su apache -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"

mysql -pcentreon -e "GRANT ALL ON *.* to 'root'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
mysql -e "GRANT ALL ON *.* to 'root'@'%' IDENTIFIED BY 'centreon' WITH GRANT OPTION"

mysql centreon < /tmp/sql/standard.sql
mysql centreon < /tmp/sql/media.sql
mysql centreon < /tmp/sql/openldap.sql

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml
