#!/bin/sh

echo "Starting PHP-FPM..."

mkdir -p /run/php

# Disable systemd notifications (not available in containers)
sed -i 's/^systemd_interval\s*=.*/systemd_interval = 0/' /etc/php/8.4/fpm/php-fpm.conf 2>/dev/null || true

systemctl start php8.4-fpm
echo "PHP-FPM started."
