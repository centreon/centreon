#!/bin/bash
set -ex

export CYPRESS_CACHE_FOLDER=$PWD/cypress_cache
export HOME=$PWD/cache

cd $MODULE/tests/e2e

npm ci

$(npm bin)/cypress run --quiet --browser chrome --reporter mochawesome --reporter-options reportDir="cypress/results/reports",overwrite=false,html=false,json=true