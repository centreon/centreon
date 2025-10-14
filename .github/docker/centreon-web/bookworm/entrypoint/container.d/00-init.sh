#!/bin/sh

rm -f /tmp/docker.ready

mkdir -p /etc/centreon/config.d /var/cache/centreon/ /var/lib/centreon/centcore
chown -R centreon:centreon /etc/centreon /var/lib/centreon/centcore
chown -R www-data:www-data /var/cache/centreon/

chmod 775 /etc/centreon/ /etc/centreon/config.d/ /var/lib/centreon/centcore


if [ "$(id -u)" = 0 ]; then
    rsync_options="-rlDog --chown www-data:www-data"
else
    rsync_options="-rlD"
fi
rsync $rsync_options --delete /usr/share/centreon/ /var/www/html/centreon/
chown -R www-data:www-data /var/www/html/centreon
