#!/bin/bash

# Only enable debug mode if requested
if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

# Install latest Centreon plugins in background (optional, can be disabled with SKIP_PLUGIN_INSTALL=true)
if [ "${SKIP_PLUGIN_INSTALL}" != "true" ]; then
  (
    export DEBIAN_FRONTEND=noninteractive
    sudo apt-get update -qq
    sudo apt-get install -y --no-install-recommends centreon-plugin* > /proc/1/fd/1 2>&1 || true
    # Cleanup after apt operations to save space
    sudo apt-get clean
    sudo rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
  ) &
fi

# create / reuse venv directory
if [ ! -d ./venv ]; then
  python3 -m venv ./venv
fi
# activate venv
. ./venv/bin/activate

# Install Python dependencies (--no-cache-dir reduces image size)
pip install --no-cache-dir fastapi uvicorn

# execute the container command so signals are forwarded
exec "$@"

