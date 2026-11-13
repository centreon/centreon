#!/bin/sh

# Slim image: no systemctl, crond is started directly (it daemonizes).
echo "Starting crond daemon..."
/usr/sbin/crond
echo "crond daemon started."
