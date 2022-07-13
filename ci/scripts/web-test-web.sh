#!/bin/bash 

cd centreon
npm ci
npm run eslint -- -o checkstyle-fe.xml -f checkstyle
npm run test:coverage -- --ci --reporters=jest-junit