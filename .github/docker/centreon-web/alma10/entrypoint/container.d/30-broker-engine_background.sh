#!/bin/sh

echo "Starting Centreon Broker (cbd)..."
systemctl start cbd
echo "Centreon Broker (cbd) started."

echo "Starting Centreon Engine (centengine)..."
systemctl start centengine
echo "Centreon Engine (centengine) started."
