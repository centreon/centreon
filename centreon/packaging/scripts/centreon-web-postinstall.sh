#!/bin/bash

manageUsersAndGroups() {
  echo "Managing users and groups for apache ..."
  if [ "$1" = "rpm" ]; then
    usermod apache -a -G nagios,centreon-engine,centreon-broker,centreon-gorgone,centreon
    usermod nagios -a -G apache
    usermod centreon-gorgone -a -G apache
    usermod centreon -a -G apache
  else
    usermod www-data -a -G centreon-engine,centreon-broker,centreon-gorgone,centreon
    usermod centreon-gorgone -a -G www-data
    usermod centreon -a -G www-data
  fi
}

updateConfigurationFiles() {
  export MIN=$(awk 'BEGIN{srand(); print int(rand()*60)}')
  export HOUR=$(awk 'BEGIN{srand(); print int(rand()*24)}')
  sed -i -E "s/0\s0(.*)centreon\-send\-stats\.php(.*)/$MIN $HOUR\1centreon-send-stats.php\2/" /etc/cron.d/centreon

  # Create HASH secret for Symfony application
  echo "Updating APP_SECRET in centreon environment file ..."
  REPLY=($(dd if=/dev/urandom bs=32 count=1 status=none | /usr/bin/php -r "echo bin2hex(fread(STDIN, 32));")); sed -i "s/%APP_SECRET%/$REPLY/g" /usr/share/centreon/.env*

  echo "Updating centreon perl configuration files to central mode ..."
  sed -i -e "s/\$instance_mode = \"poller\";/\$instance_mode = \"central\";/g" /etc/centreon/conf.pm
  sed -i -e 's/mode => 1/mode => 0/g' /etc/centreon/centreontrapd.pm
}

setTimezone() {
  PHP_TIMEZONE=$(php -r '
    $timezoneName = timezone_name_from_abbr(trim(shell_exec("date \"+%Z\"")));
    if (date_default_timezone_set($timezoneName) === false) {
      $timezoneName = "UTC";
    }
    echo $timezoneName;
  ' 2>/dev/null || echo "UTC")

  echo "Setting php timezone to ${PHP_TIMEZONE} ..."
  if [ "$1" = "rpm" ]; then
    sed -i "s#^date.timezone = .*#date.timezone = ${PHP_TIMEZONE}#" /etc/php.d/50-centreon.ini
  else
    sed -i "s#^date.timezone = .*#date.timezone = ${PHP_TIMEZONE}#" /etc/php/8.2/mods-available/centreon.ini
  fi
}

updateGorgoneConfiguration() {
  # make sure that gorgone configuration file has the central id set
  if [[ -f /etc/centreon-gorgone/config.d/40-gorgoned.yaml && ! "$(cat /etc/centreon-gorgone/config.d/40-gorgoned.yaml | tr -d '\n')" =~ gorgonecore.*id:.*modules: ]]; then
    echo "Forcing central id to gorgone configuration ..."
    sed -Ei 's/(gorgonecore:)/\1\n    id: 1/g' /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  fi
}

manageLocales() {
  if [ "$1" = "deb" ]; then
    # Set locales on system to use in translation
    echo "Generating locales for Centreon translation ..."
    sed -i \
        -e '/^#.* en_US.UTF-8 /s/^#//' \
        -e '/^#.* fr_FR.UTF-8 /s/^#//' \
        -e '/^#.* pt_PT.UTF-8 /s/^#//' \
        -e '/^#.* pt_BR.UTF-8 /s/^#//' \
        -e '/^#.* es_ES.UTF-8 /s/^#//' \
        /etc/locale.gen > /dev/null 2>&1 || :
    locale-gen > /dev/null 2>&1 || :
  fi
}

manageApacheAndPhpFpm() {
  echo "Managing apache and php fpm configuration and services ..."
  if [ "$1" = "rpm" ]; then
    systemctl restart php-fpm || :
    systemctl restart httpd || :
  else
    update-alternatives --set php /usr/bin/php8.2 > /dev/null 2>&1 || :
    a2enmod headers proxy_fcgi setenvif proxy rewrite alias proxy proxy_fcgi > /dev/null 2>&1 || :
    a2enconf php8.2-fpm > /dev/null 2>&1 || :
    a2dissite 000-default > /dev/null 2>&1 || :
    a2ensite centreon > /dev/null 2>&1 || :
    systemctl restart php8.2-fpm || :
    systemctl restart apache2 || :
  fi
}

rebuildSymfonyCache() {
  echo "Rebuilding Centreon application cache ..."
  rm -rf /var/cache/centreon/symfony

  if [ "$1" = "rpm" ]; then
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"
  else
    su - www-data -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"
  fi
}

fixSymfonyCacheRights() {
  # MON-69138
  SYMFONY_CACHE_DIR="/var/cache/centreon/symfony"
  if [ -d "$SYMFONY_CACHE_DIR" ]; then
    if [ "$1" = "rpm" ]; then
      chown -R apache:apache "$SYMFONY_CACHE_DIR"
    else
      chown -R www-data:www-data "$SYMFONY_CACHE_DIR"
    fi
    chmod 755 "$SYMFONY_CACHE_DIR"
  fi
}

package_type="rpm"
if  [ "$1" = "configure" ]; then
  package_type="deb"
fi

action="$1"
if  [ "$1" = "configure" ] && [ -z "$2" ]; then
  # Alpine linux does not pass args, and deb passes $1=configure
  action="install"
elif [ "$1" = "configure" ] && [ -n "$2" ]; then
  # deb passes $1=configure $2=<current version>
  action="upgrade"
fi

case "$action" in
  "1" | "install")
    manageUsersAndGroups $package_type
    updateConfigurationFiles
    updateGorgoneConfiguration
    manageLocales $package_type
    manageApacheAndPhpFpm $package_type
    fixSymfonyCacheRights $package_type
    ;;
  "2" | "upgrade")
    manageUsersAndGroups $package_type
    updateConfigurationFiles
    updateGorgoneConfiguration
    manageLocales $package_type
    manageApacheAndPhpFpm $package_type
    fixSymfonyCacheRights $package_type
    rebuildSymfonyCache $package_type
    ;;
  *)
    # $1 == version being installed
    manageUsersAndGroups $package_type
    ;;
esac
