#!/bin/sh

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

if [ $(mysql -N -s -h${MYSQL_HOST} -u root -e \
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

  sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
  sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml

  mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
  mysql -h${MYSQL_HOST} -uroot -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

  if [ $CENTREON_DATASET = "1" ]; then
    echo "CENTREON_DATASET environment variable is set, dump will be inserted."
    DATA_DUMP_DIR="/usr/local/src/sql/data"
    for file in `ls $DATA_DUMP_DIR` ; do
      echo "Inserting dump $file ..."
      mysql -h${MYSQL_HOST} -uroot centreon < $DATA_DUMP_DIR/$file
    done
  fi
fi

setAdminLanguage() {
  if [ -z "$1" ]; then
    echo "Language not set"
    return
  fi

  echo "Setting language to $1"

  mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE contact SET contact_lang = '$1.UTF-8' WHERE contact_alias = 'admin'"
}

case "$CENTREON_LANG" in
  de*)
    setAdminLanguage "de_DE"
    ;;
  en*)
    setAdminLanguage "en_US"
    ;;
  es*)
    setAdminLanguage "es_ES"
    ;;
  fr*)
    setAdminLanguage "fr_FR"
    ;;
  pt_BR)
    setAdminLanguage "pt_BR"
    ;;
  pt*)
    setAdminLanguage "pt_PT"
    ;;
  "")
    ;;
  *)
    echo "Language $CENTREON_LANG not supported"
    ;;
esac

su www-data -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"
