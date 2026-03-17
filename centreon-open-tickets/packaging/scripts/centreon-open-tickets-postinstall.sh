#!/bin/bash

# rebuild symfony cache on upgrade
if [ -f /etc/centreon/centreon.conf.php ]; then
  if  [ "$1" = "configure" ]; then # deb
    rm -rf /var/cache/centreon/symfony
    su - www-data -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear --no-warmup -q" || :
  fi
fi

