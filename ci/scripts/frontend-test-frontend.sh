#!/bin/bash 

cd centreon-frontend
npm ci --legacy-peer-deps

cd packages/ui-context
npm ci --legacy-peer-deps
npm run eslint -- -o checkstyle.xml -f checkstyle

cd ../centreon-ui
npm ci --legacy-peer-deps
npm run eslint -- -o checkstyle.xml -f checkstyle
npm run build:storybook
npm t -- --ci --reporters=jest-junit --maxWorkers=1