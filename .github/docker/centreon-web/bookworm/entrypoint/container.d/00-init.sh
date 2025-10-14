#!/bin/sh

rm -f /tmp/docker.ready


if [ "$(id -u)" = 0 ]; then
    rsync_options="-rlDog --chown $user:$group"
else
    rsync_options="-rlD"
fi
rsync $rsync_options --delete /usr/share/centreon/ /var/www/html/centreon/
chown -R www-data:www-data /var/www/html/centreon
