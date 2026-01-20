#!/bin/sh

# Controls database partitioning during Centreon installation.
# Default to "1" (enabled) to preserve the standard installation behavior
# when DATABASE_PARTITIONING is not explicitly set.
DATABASE_PARTITIONING=${DATABASE_PARTITIONING:-1}

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json

if [ ! -f /etc/centreon/centreon.conf.php ] && [ -d /usr/share/centreon/www/install ]; then
  cd /usr/share/centreon/www/install/steps/process

  echo "Creating Centreon configuration files..."
  su apache -s /bin/bash -c "php configFileSetup.php"

  if [ $(mysql -N -s -h${MYSQL_HOST} -u root -e \
    "SELECT count(*) from information_schema.tables WHERE \
        table_schema='centreon' and table_name='nagios_server'") -eq 1
  ]; then
    echo "Centreon is already installed."

    echo "Creating Centreon database user..."
    su apache -s /bin/bash -c "php createDbUser.php"
  else
    echo "Installing Centreon configuration database..."
    su apache -s /bin/bash -c "php installConfigurationDb.php"

    echo "Installing Centreon storage database..."
    su apache -s /bin/bash -c "php installStorageDb.php"

    echo "Creating Centreon database user..."
    su apache -s /bin/bash -c "php createDbUser.php"

    echo "Inserting base configuration ..."
    su apache -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"

    if [ "$DATABASE_PARTITIONING" = "1" ]; then
      echo "DATABASE_PARTITIONING environment variable is set, creating partition tables..."
      su apache -s /bin/bash -c "php partitionTables.php"
    fi

    mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
    mysql -h${MYSQL_HOST} -uroot -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

    if [ "$CENTREON_DATASET" = "1" ]; then
      echo "CENTREON_DATASET environment variable is set, dump will be inserted."
      DATA_DUMP_DIR="/usr/local/src/sql/data"
      for file in `ls $DATA_DUMP_DIR` ; do
        echo "Inserting dump $file ..."
        mysql -h${MYSQL_HOST} -uroot centreon < $DATA_DUMP_DIR/$file
      done
    fi
  fi

  echo "Creating engine context configuration..."
  su apache -s /bin/bash -c "php createEngineContextConfiguration.php"

  echo "Generating Centreon cache..."
  su apache -s /bin/bash -c "php generationCache.php"

  echo "Disabling statistics collection..."
  mysql -h${MYSQL_HOST} -uroot centreon -e "DELETE FROM options WHERE \`key\` = 'send_statistics'"
  mysql -h${MYSQL_HOST} -uroot centreon -e "INSERT INTO options (\`key\`, \`value\`) VALUES ('send_statistics', '0')"

  cd -
fi

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml


setAdminLanguage() {
  if [ -z "$1" ]; then
    echo "Language not set"
    return
  fi

  echo "Setting language to $1"

  mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE contact SET contact_lang = '$1.UTF-8' WHERE contact_alias = 'admin'"
}

installLanguagePack() {
  if [ -z "$1" ]; then
    echo "Language not set"
    return
  fi

  echo "Installing language pack for $1"

  dnf install -y --disablerepo='centreon*' --disablerepo='epel*' glibc-langpack-$1
}

case "$CENTREON_LANG" in
  de*)
    installLanguagePack "de"
    setAdminLanguage "de_DE"
    ;;
  en*)
    setAdminLanguage "en_US"
    ;;
  es*)
    installLanguagePack "es"
    setAdminLanguage "es_ES"
    ;;
  fr*)
    installLanguagePack "fr"
    setAdminLanguage "fr_FR"
    ;;
  pt_BR)
    installLanguagePack "pt"
    setAdminLanguage "pt_BR"
    ;;
  pt*)
    installLanguagePack "pt"
    setAdminLanguage "pt_PT"
    ;;
  "")
    ;;
  *)
    echo "Language $CENTREON_LANG not supported"
    ;;
esac

su apache -s /bin/bash -c "rm -rf /usr/share/centreon/www/install"
