#!/bin/bash 
set -ex

cd centreon
npm ci --legacy-peer-deps
npm run eslint -- -o checkstyle-fe.xml -f checkstyle
npm run test:coverage -- --ci --reporters=jest-junit