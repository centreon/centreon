#!/bin/bash

rebuildSymfonyCache() {
  if command -v rpm &> /dev/null; then
    rm -rf /var/cache/centreon/symfony
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear --no-warmup -q" || :
  fi
}

if [ -f /etc/centreon/centreon.conf.php ]; then
  rebuildSymfonyCache
fi
