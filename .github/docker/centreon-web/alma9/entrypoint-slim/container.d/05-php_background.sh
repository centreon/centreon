#!/bin/sh

# Slim image: no systemctl, PHP-FPM is started directly (it daemonizes).
echo "Starting PHP-FPM..."
mkdir -p /run/php-fpm
/usr/sbin/php-fpm
echo "PHP-FPM started."
