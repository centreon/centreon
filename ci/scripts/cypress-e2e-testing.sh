#!/bin/bash
set -ex

export CYPRESS_CACHE_FOLDER=$PWD/cypress_cache
export HOME=$PWD/cache

cd $MODULE/tests/e2e

pnpm cypress run --quiet --browser chrome --reporter junit --reporter-options mochaFile=cypress-result.xml,toConsole=false