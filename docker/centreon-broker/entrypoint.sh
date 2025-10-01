#!/bin/bash
set -e

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
