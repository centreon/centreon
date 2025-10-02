#!/bin/bash
set -e

(
  export DEBIAN_FRONTEND=noninteractive
  sudo apt-get update -qq
  sudo apt-get install -y centreon-plugin* >/var/log/apt-runtime.log 2>&1 || true
) &

# create / reuse venv directory
if [ ! -d ./venv ]; then
  python3 -m venv ./venv
fi
# activate venv
. ./venv/bin/activate

pip install --upgrade pip
pip install fastapi uvicorn

# execute the container command so signals are forwarded
exec "$@"

