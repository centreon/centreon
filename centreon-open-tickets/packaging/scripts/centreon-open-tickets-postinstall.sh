#!/bin/bash

# rebuild symfony cache on upgrade
if [ -f /etc/centreon/centreon.conf.php ]; then
  if command -v rpm &> /dev/null; then
    APACHE_GROUP="apache"
  else
    APACHE_GROUP="www-data"
  fi
  su - $APACHE_GROUP -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear --no-warmup"
fi

