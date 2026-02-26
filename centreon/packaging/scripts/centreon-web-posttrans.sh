#!/bin/bash

rebuildSymfonyCache() {
  if command -v rpm &> /dev/null; then
    echo "Rebuilding Centreon application cache ..."
    rm -rf /var/cache/centreon/symfony
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear -q" || :
    rm -rf /var/cache/centreon/symfony.new
    su - apache -s /bin/bash -c "/usr/share/centreon/bin/console.new cache:clear -q" || :
  fi
}

if [ -f /etc/centreon/centreon.conf.php ]; then
  rebuildSymfonyCache
fi
