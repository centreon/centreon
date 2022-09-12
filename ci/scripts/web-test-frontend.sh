#!/bin/bash 
set -ex

export CYPRESS_CACHE_FOLDER=$PWD/cypress_cache

cd centreon
npm ci --legacy-peer-deps
npm run test:coverage -- --ci
