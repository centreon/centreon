#!/bin/sh

echo "Starting PHP-FPM..."

# Disable systemd notifications (not available in containers)
sed -i 's/^systemd_interval\s*=.*/systemd_interval = 0/' /etc/php-fpm.conf 2>/dev/null || true

# Expose _CENTREON_LOG_ as an OS env var so Symfony %env(_CENTREON_LOG_)% can resolve it
sed -i '/^\[www\]/a env[_CENTREON_LOG_] = /var/log/centreon' /etc/php-fpm.d/www.conf

systemctl start php-fpm
echo "PHP-FPM started."
