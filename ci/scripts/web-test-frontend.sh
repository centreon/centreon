#!/bin/bash 
set -ex

cd centreon
npm ci --legacy-peer-deps
npm run test:coverage -- --ci