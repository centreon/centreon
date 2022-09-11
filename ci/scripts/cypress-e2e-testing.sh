#!/bin/bash
set -ex

export CYPRESS_CACHE_FOLDER=$PWD/cypress_cache
export CYPRESS_VERIFY_TIMEOUT=100000
export HOME=$PWD/cache
# export DEBUG="cypress:server:browsers,cypress:server:video"

cd $MODULE/tests/e2e

npm ci

$(npm bin)/cypress run --quiet --browser chrome --reporter json