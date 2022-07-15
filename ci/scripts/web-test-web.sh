#!/bin/bash 

set -ex

cd centreon-frontend
npm ci --legacy-peer-deps

cd packages/ui-context
npm ci --legacy-peer-deps
npm run eslint -- -o checkstyle.xml -f checkstyle

cd ../centreon-ui
npm ci --legacy-peer-deps
npm run eslint -- -o checkstyle.xml -f checkstyle
npm t -- --ci --reporters=jest-junit
npm run build:storybook