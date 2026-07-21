#!/bin/sh

echo "Starting PHP-FPM..."

# Disable systemd notifications (not available in containers)
sed -i 's/^systemd_interval\s*=.*/systemd_interval = 0/' /etc/php-fpm.conf 2>/dev/null || true

systemctl start php-fpm
echo "PHP-FPM started."
