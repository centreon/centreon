const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('@centreon/frontend-core/jest'), {
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  roots: ['<rootDir>/src/'],
});
