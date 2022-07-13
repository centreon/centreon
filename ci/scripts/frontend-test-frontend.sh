#!/bin/bash 

cd centreon-frontend
npm ci --legacy-peer-deps

cd packages/ui-context
npm ci --legacy-peer-deps


# Run frontend unit tests and code style.
npm run eslint -- -o checkstyle.xml -f checkstyle