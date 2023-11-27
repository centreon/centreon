#!/bin/bash

manageUsersAndGroups() {
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
  REPLY=($(dd if=/dev/urandom bs=32 count=1 status=none | /usr/bin/php -r "echo bin2hex(fread(STDIN, 32));")); sed -i "s/%APP_SECRET%/$REPLY/g" /usr/share/centreon/.env*

  sed -i -e "s/\$instance_mode = \"poller\";/\$instance_mode = \"central\";/g" /etc/centreon/conf.pm
  sed -i -e 's/mode => 1/mode => 0/g' /etc/centreon/centreontrapd.pm
}

updateEngineBrokerRights() {
  # Change right for Centreon Engine and Centreon Broker configuration files
  if [ -d /etc/centreon-broker ] && [ "$(getent passwd centreon-broker)" ]; then
      chown centreon-broker:centreon /etc/centreon-broker/*
  fi
  if [ -d /etc/centreon-engine ] && [ "$(getent passwd centreon-engine)" ]; then
      chown centreon-engine:centreon /etc/centreon-engine/*
  fi
}

updateGorgoneConfiguration() {
  # make sure that gorgone configuration file has the central id set
  if [[ ! "$(sed '5,5!d' /etc/centreon-gorgone/config.d/40-gorgoned.yaml)" =~ ^.*id:.*$ ]]; then
      sed -i "5s/.*/    id: 1/" /etc/centreon-gorgone/config.d/40-gorgoned.yaml
  fi
}

restartApacheAndPhpFpm() {
  if [ "$1" = "rpm" ]; then
    systemctl try-restart httpd || :
    systemctl try-restart php-fpm || :
  else
    systemctl try-restart apache2 || :
    systemctl try-restart php8.1-fpm || :
  fi
}

rebuildSymfonyCache() {
  rm -rf /var/cache/centreon/symfony

  if [ "$1" = "rpm" ]; then
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"
  else
    su - www-data -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"
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
    restartApacheAndPhpFpm $package_type
    ;;
  "2" | "upgrade")
    manageUsersAndGroups $package_type
    updateConfigurationFiles
    updateGorgoneConfiguration
    restartApacheAndPhpFpm $package_type
    updateEngineBrokerRights
    rebuildSymfonyCache $package_type
    ;;
  *)
    # $1 == version being installed
    manageUsersAndGroups $package_type
    ;;
esac
