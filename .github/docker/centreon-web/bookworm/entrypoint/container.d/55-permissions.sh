#!/bin/sh
mkdir -p /var/log/centreon/
touch /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log

# Only perform full permission reset if marker file doesn't exist
if [ ! -f /var/cache/centreon/.permissions_set ]; then
  echo "Setting initial permissions (first run)..."
  chown -R centreon:centreon /var/lib/centreon/ /var/log/centreon/ /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log
  chown -R www-data:www-data /var/cache/centreon/
  chmod -R 775 /var/lib/centreon/ /var/cache/centreon/
  chmod 1230 /var/log/centreon/centreon-web.log

  # Ensure config generation directories have correct permissions
  mkdir -p /var/cache/centreon/config
  chown -R www-data:www-data /var/cache/centreon/config/
  chmod -R 775 /var/cache/centreon/config/

  # Create marker file to skip on subsequent runs
  mkdir -p /var/cache/centreon
  touch /var/cache/centreon/.permissions_set
  echo "Permissions set successfully"
else
  echo "Permissions already set, skipping recursive chown/chmod"
  # Only ensure critical directories are owned correctly
  chown centreon:centreon /var/log/centreon/ /var/log/php8.2-fpm-centreon-error.log /var/log/centreon/centreon-web.log
  chown www-data:www-data /var/cache/centreon/symfony 2>/dev/null || true

  # Ensure config directories exist and have correct permissions (needed for config generation)
  mkdir -p /var/cache/centreon/config
  chown -R www-data:www-data /var/cache/centreon/config/ 2>/dev/null || true
  chmod -R 775 /var/cache/centreon/config/ 2>/dev/null || true
fi

# Always clear Symfony cache (required for proper operation)
su - www-data -s /bin/bash -c "php /usr/share/centreon/bin/console cache:clear"
