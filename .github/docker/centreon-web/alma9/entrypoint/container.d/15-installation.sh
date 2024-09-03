#!/bin/sh

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

# sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/centreon.conf.php
# sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/conf.pm
# sed -i "s/localhost/${MYSQL_HOST}/g" /etc/centreon/config.d/10-database.yaml

mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
