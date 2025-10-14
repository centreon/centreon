#!/bin/sh

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
export MYSQL_PWD="centreon"
export MYSQL_HOST="db"

sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json

if [ ! -f /etc/centreon/centreon.conf.php ] && [ -d /usr/share/centreon/www/install ]; then
  cd /usr/share/centreon/www/install/steps/process

  if [ $(mysql -N -s -h${MYSQL_HOST} -u root -e \
      "SELECT count(*) from information_schema.tables WHERE \
          table_schema='centreon' and table_name='nagios_server'") -eq 1 ]; then
      echo "Centreon is already installed."
      su www-data -s /bin/bash -c "php configFileSetup.php"
      su www-data -s /bin/bash -c "php createDbUser.php"
  else
    su www-data -s /bin/bash -c "php configFileSetup.php"
    su www-data -s /bin/bash -c "php installConfigurationDb.php"
    su www-data -s /bin/bash -c "php installStorageDb.php"
    su www-data -s /bin/bash -c "php createDbUser.php"
    su www-data -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
    su www-data -s /bin/bash -c "php partitionTables.php"

  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "DELETE FROM centreon.cfg_centreonbroker_info WHERE config_group = 'output' AND config_group_id = '1'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.cfg_centreonbroker_info SET config_id = '1', config_group_id = 1 WHERE config_id = '2' and config_group = 'output'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "DELETE FROM centreon.cfg_centreonbroker WHERE config_name = 'central-rrd-master'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "CREATE USER IF NOT EXISTS 'centreon'@'%' IDENTIFIED BY 'centreon'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.options SET \`value\` = 'gorgone' WHERE \`key\` = 'gorgone_api_address'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "GRANT ALL ON *.* TO 'centreon'@'%'"

    if [ "$CENTREON_DATASET" = "1" ]; then
      echo "CENTREON_DATASET environment variable is set, dump will be inserted."
      DATA_DUMP_DIR="/usr/local/src/sql/data"
      for file in `ls $DATA_DUMP_DIR` ; do
        echo "Inserting dump $file ..."
        mysql -h${MYSQL_HOST} -uroot centreon < $DATA_DUMP_DIR/$file
      done
    fi
  fi

  su www-data -s /bin/bash -c "php createEngineContextConfiguration.php"
  su www-data -s /bin/bash -c "php generationCache.php"
  cd -
fi

cat <<EOF > /usr/sbin/centengine
#!/bin/sh
echo "Dummy centengine called"
echo "Total Errors:   0"
exit 0
EOF
chmod +x /usr/sbin/centengine


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

su www-data -s /bin/bash -c "rm -rf /var/www/html/centreon/www/install/"
