#!/bin/sh
set -e

# Symfony's lock component picks SemaphoreStore vs FlockStore at the moment
# the cache is compiled (symfony/lock), and postinstall.sh/posttrans.sh only
# rebuild the cache once /etc/centreon/centreon.conf.php exists — never at
# package-build time. Same rule here: no warmup baked into the image, and
# guarded the same way at container start.
if [ -f /etc/centreon/centreon.conf.php ]; then
  echo "Rebuilding Centreon application cache..."
  rm -rf /var/cache/centreon/symfony
  su -s /bin/bash www-data -c "/usr/share/centreon/bin/console cache:clear -q" || true
  rm -rf /var/cache/centreon/symfony.new
  su -s /bin/bash www-data -c "/usr/share/centreon/bin/console.new cache:clear -q" || true
else
  echo "No /etc/centreon/centreon.conf.php yet — skipping cache warmup (nothing to do until install/init runs)."
fi
