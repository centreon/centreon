#!/bin/bash

# Only enable debug mode if requested
if [ "${DEBUG}" = "true" ] || [ "${DEBUG}" = "1" ]; then
  set -x
  echo "Debug mode enabled"
fi

set -e

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
