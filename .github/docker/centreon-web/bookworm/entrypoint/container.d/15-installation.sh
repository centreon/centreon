#!/bin/sh

if [ $(mysql -N -s -h${MYSQL_HOST} -u root -p"${MYSQL_ROOT_PASSWORD}" -e \
    "SELECT count(*) from information_schema.tables WHERE \
        table_schema='centreon' and table_name='nagios_server'") -eq 1 ]; then
    echo "Centreon is already installed."
else
  sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json

  cd /usr/share/centreon/www/install/steps/process
  su www-data -s /bin/bash -c "php configFileSetup.php"
  su www-data -s /bin/bash -c "php installConfigurationDb.php"
  su www-data -s /bin/bash -c "php installStorageDb.php"
  su www-data -s /bin/bash -c "php createDbUser.php"
  su www-data -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
  su www-data -s /bin/bash -c "php partitionTables.php"
  su www-data -s /bin/bash -c "php generationCache.php"
  cd -

  sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
  sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

  mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
  mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

  if [ $CENTREON_DATASET = "1" ]; then
    echo "CENTREON_DATASET environment variable is set, dump will be inserted."
    DATA_DUMP_DIR="/usr/local/src/sql/data"
    for file in `ls $DATA_DUMP_DIR` ; do
      echo "Inserting dump $file ..."
      mysql -h${MYSQL_HOST} -uroot -p"${MYSQL_ROOT_PASSWORD}" centreon < $DATA_DUMP_DIR/$file
    done
  fi
fi

su www-data -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"
