#!/bin/sh

# Slim image: no systemctl, Apache is started directly (it daemonizes).
echo "Starting Apache (httpd)..."
mkdir -p /run/httpd
/usr/sbin/httpd
echo "Apache (httpd) started."
