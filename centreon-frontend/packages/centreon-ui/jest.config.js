const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('@centreon/frontend-config/jest'), {
  roots: ['<rootDir>/src/'],
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
});
