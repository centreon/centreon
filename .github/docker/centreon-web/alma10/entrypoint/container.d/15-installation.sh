#!/bin/bash
set -euo pipefail

# Controls database partitioning during Centreon installation.
# Default to "1" (enabled) to preserve the standard installation behavior
# when DATABASE_PARTITIONING is not explicitly set.
DATABASE_PARTITIONING=${DATABASE_PARTITIONING:-1}

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

# Database names default to the stock values but can be overridden.
MYSQL_DB_CONFIGURATION="${MYSQL_DB_CONFIGURATION:-centreon}"
MYSQL_DB_STORAGE="${MYSQL_DB_STORAGE:-centreon_storage}"

sed -i "s/localhost/${MYSQL_HOST}/g" /usr/share/centreon/www/install/tmp/database.json
sed -i "s/\"db_configuration\": \"[^\"]*\"/\"db_configuration\": \"${MYSQL_DB_CONFIGURATION}\"/" /usr/share/centreon/www/install/tmp/database.json
sed -i "s/\"db_storage\": \"[^\"]*\"/\"db_storage\": \"${MYSQL_DB_STORAGE}\"/" /usr/share/centreon/www/install/tmp/database.json

if [ ! -f /etc/centreon/centreon.conf.php ] && [ -d /usr/share/centreon/www/install ]; then
  cd /usr/share/centreon/www/install/steps/process

  MYSQL_FK_CHECKS=$(mysql -N -s -h"${MYSQL_HOST}" -uroot -e "SELECT @@GLOBAL.foreign_key_checks")
  MYSQL_UNIQUE_CHECKS=$(mysql -N -s -h"${MYSQL_HOST}" -uroot -e "SELECT @@GLOBAL.unique_checks")
  MYSQL_INNODB_FLUSH=$(mysql -N -s -h"${MYSQL_HOST}" -uroot -e "SELECT @@GLOBAL.innodb_flush_log_at_trx_commit")
  MYSQL_SYNC_BINLOG=$(mysql -N -s -h"${MYSQL_HOST}" -uroot -e "SELECT @@GLOBAL.sync_binlog")

  restore_mysql_settings() {
    mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL foreign_key_checks=${MYSQL_FK_CHECKS}"
    mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL unique_checks=${MYSQL_UNIQUE_CHECKS}"
    mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL innodb_flush_log_at_trx_commit=${MYSQL_INNODB_FLUSH}"
    mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL sync_binlog=${MYSQL_SYNC_BINLOG}"
  }
  trap restore_mysql_settings EXIT

  mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL foreign_key_checks=0"
  mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL unique_checks=0"
  mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL innodb_flush_log_at_trx_commit=2"
  mysql -h"${MYSQL_HOST}" -uroot -e "SET GLOBAL sync_binlog=0"

  echo "Creating Centreon configuration files..."
  su apache -s /bin/bash -c "php configFileSetup.php" > /dev/null

  if [ "$(mysql -N -s -h"${MYSQL_HOST}" -u root -e "SELECT count(*) from information_schema.tables WHERE table_schema='${MYSQL_DB_CONFIGURATION}' and table_name='nagios_server'")" -eq 1 ]; then
    echo "Centreon is already installed."

    echo "Creating Centreon database user..."
    su apache -s /bin/bash -c "php createDbUser.php" > /dev/null
  else
    echo "Installing Centreon configuration database..."
    su apache -s /bin/bash -c "php installConfigurationDb.php" > /dev/null

    echo "Installing Centreon storage database..."
    su apache -s /bin/bash -c "php installStorageDb.php" > /dev/null

    echo "Creating Centreon database user..."
    su apache -s /bin/bash -c "php createDbUser.php" > /dev/null

    echo "Inserting base configuration..."
    su apache -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php" > /dev/null

    if [ "$DATABASE_PARTITIONING" = "1" ]; then
      echo "Creating database partition tables..."
      su apache -s /bin/bash -c "php partitionTables.php" > /dev/null
    fi

    mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "UPDATE cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
    mysql -h"${MYSQL_HOST}" -uroot -e "GRANT ALL ON *.* to 'centreon'@'%' WITH GRANT OPTION"

    if [ "${CENTREON_DATASET:-0}" = "1" ]; then
      echo "CENTREON_DATASET environment variable is set, dump will be inserted."
      DATA_DUMP_DIR="/usr/local/src/sql/data"
      for file in "$DATA_DUMP_DIR"/*; do
        [ -e "$file" ] || continue
        echo "Inserting dump $(basename "$file") ..."
        mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" < "$file"
      done
    fi
  fi

  echo "Generating Centreon cache..."
  su apache -s /bin/bash -c "php generationCache.php" > /dev/null

  echo "Creating engine context configuration..."
  su apache -s /bin/bash -c "php createEngineContextConfiguration.php" > /dev/null

  echo "Disabling statistics collection..."
  mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "DELETE FROM options WHERE \`key\` = 'send_statistics'"
  mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "INSERT INTO options (\`key\`, \`value\`) VALUES ('send_statistics', '0')"

  restore_mysql_settings
  trap - EXIT

  cd - > /dev/null
fi

sed -i 's#severity=error#severity=debug#' /etc/sysconfig/gorgoned
if [[ -f /etc/centreon-gorgone/config.d/40-gorgoned.yaml ]]; then
  if grep -Pzo '(?s)gorgonecore:.*?(?=\n\S|\Z)' /etc/centreon-gorgone/config.d/40-gorgoned.yaml | grep -q 'id:'; then
    sed -Ei '/^gorgonecore:/,/^[^[:space:]]/ s/^([[:space:]]*id:).*/\1 1/' /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  else
    sed -Ei '/^gorgonecore:/a\    id: 1' /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  fi
fi
sed -i 's#enable: true#enable: false#' /etc/centreon-gorgone/config.d/50-centreon-audit.yaml


setAdminLanguage() {
  if [ -z "${1:-}" ]; then
    echo "Language not set"
    return
  fi

  echo "Setting language to $1"

  mysql -h"${MYSQL_HOST}" -uroot "${MYSQL_DB_CONFIGURATION}" -e "UPDATE contact SET contact_lang = '$1.UTF-8' WHERE contact_alias = 'admin'"
}

installLanguagePack() {
  if [ -z "${1:-}" ]; then
    echo "Language not set"
    return
  fi

  echo "Installing language pack for $1"

  dnf install -y --disablerepo='centreon*' --disablerepo='epel*' "glibc-langpack-$1"
}

case "${CENTREON_LANG:-}" in
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
