#!/bin/sh

echo "Starting cron daemon..."
systemctl start cron
echo "cron daemon started."
