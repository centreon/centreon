#!/bin/sh

# Avoid to display mysql warning: Using a password on the command line interface can be insecure.
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

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
