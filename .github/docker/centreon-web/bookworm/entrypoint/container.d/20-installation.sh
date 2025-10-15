#!/bin/sh

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
: "${MYSQL_HOST:=db}"
: "${MYSQL_PWD:=centreon}"


INSTALL_DIR="/usr/share/centreon/www/install"
PROCESS_DIR="$INSTALL_DIR/steps/process"
UPGRADE_DIR="$INSTALL_DIR/step_upgrade/process/"
if [ -f "$INSTALL_DIR/tmp/database.json" ]; then
  sed -i "s/localhost/${MYSQL_HOST}/g" "$INSTALL_DIR/tmp/database.json"
fi

# --------------------------
# Helper functions
# --------------------------
run_upgrade_step4() {
  echo "Running core upgrade (step4)..."
  su www-data -s /bin/bash -c "php -r '\$_POST[\"current\"]=\"$1\"; \$_POST[\"next\"]=\"$2\"; include \"$UPGRADE_DIR/process_step4.php\";'"
}

# --------------------------
# Detect if Centreon is installed
# --------------------------
if [ ! -f /etc/centreon/centreon.conf.php ]; then
  echo "Fresh Centreon installation detected"

  cd "$PROCESS_DIR"

  su www-data -s /bin/bash -c "php configFileSetup.php"
  su www-data -s /bin/bash -c "php installConfigurationDb.php"
  su www-data -s /bin/bash -c "php installStorageDb.php"
  su www-data -s /bin/bash -c "php createDbUser.php"
  su www-data -s /bin/bash -c "SERVER_ADDR='127.0.0.1' php insertBaseConf.php"
  su www-data -s /bin/bash -c "php partitionTables.php"

  # Database tweaks
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.cfg_centreonbroker_info SET config_value = '${MYSQL_HOST}' WHERE config_key = 'db_host'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "DELETE FROM centreon.cfg_centreonbroker_info WHERE config_group = 'output' AND config_group_id = '1'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.cfg_centreonbroker_info SET config_id = '1', config_group_id = 1 WHERE config_id = '2' and config_group = 'output'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "DELETE FROM centreon.cfg_centreonbroker WHERE config_name = 'central-rrd-master'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "CREATE USER IF NOT EXISTS 'centreon'@'%' IDENTIFIED BY 'centreon'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "UPDATE centreon.options SET \`value\` = 'gorgone' WHERE \`key\` = 'gorgone_api_address'"
  mysql -h${MYSQL_HOST} -uroot -p${MYSQL_PWD} -e "GRANT ALL ON *.* TO 'centreon'@'%'"

  # Optional dataset import
  if [ "$CENTREON_DATASET" = "1" ]; then
    echo "Inserting CENTREON_DATASET..."
    DATA_DUMP_DIR="/usr/local/src/sql/data"
    for file in "$DATA_DUMP_DIR"/*; do
      echo "Importing $file ..."
      mysql -h${MYSQL_HOST} -uroot centreon < "$file"
    done
  fi

  su www-data -s /bin/bash -c "php createEngineContextConfiguration.php"
  su www-data -s /bin/bash -c "php generationCache.php"

  cd -

else
  echo "Existing Centreon installation detected"

  # --------------------------
  # Get current and next version
  # --------------------------
  versions=$(su - www-data -s /bin/bash -c "php $INSTALL_DIR/get_versions.php")
  current=$(echo "$versions" | jq -r .current)
  next=$(echo "$versions" | jq -r .next)

  echo "Current version: $current"
  echo "Next version:    $next"

  # --------------------------
  # Upgrade if needed
  # --------------------------
  if [ "$current" != "$next" ] && [ -n "$next" ] && [ "$next" != "null" ]; then
    echo "Upgrade available — Update DB schema from $current to $next"
    run_upgrade_step4 "$current" "$next"
    su www-data -s /bin/bash -c "php generationCache.php"
  else
    echo "Centreon is up-to-date — no upgrade required"
  fi
fi

# --------------------------
# Dummy centengine
# --------------------------
cat <<EOF > /usr/sbin/centengine
#!/bin/sh
echo "Dummy centengine called"
echo "Total Errors:   0"
exit 0
EOF
chmod +x /usr/sbin/centengine

# --------------------------
# Admin language
# --------------------------
setAdminLanguage() {
  [ -z "$1" ] && return
  echo "Setting admin language to $1"
  mysql -h${MYSQL_HOST} -uroot centreon -e "UPDATE contact SET contact_lang = '$1.UTF-8' WHERE contact_alias = 'admin'"
}

case "$CENTREON_LANG" in
  de*) setAdminLanguage "de_DE" ;;
  en*) setAdminLanguage "en_US" ;;
  es*) setAdminLanguage "es_ES" ;;
  fr*) setAdminLanguage "fr_FR" ;;
  pt_BR) setAdminLanguage "pt_BR" ;;
  pt*) setAdminLanguage "pt_PT" ;;
  "") ;;
  *) echo "Language $CENTREON_LANG not supported" ;;
esac

# --------------------------
# Clean install folder
# --------------------------
su www-data -s /bin/bash -c "rm -rf /var/www/html/centreon/www/install/"
