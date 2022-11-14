#!/bin/bash
set -ex

export CYPRESS_CACHE_FOLDER=$PWD/cypress_cache
export HOME=$PWD/cache

npm i -g pnpm@7

cd $MODULE

pnpm install

cd tests/e2e

pnpm cypress run --quiet --browser chrome --reporter junit --reporter-options mochaFile=cypress-result.xml,toConsole=false