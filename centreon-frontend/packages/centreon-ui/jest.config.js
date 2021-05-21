const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(
  require('@centreon/frontend-config/jest/centreon-ui.js'),
  {
    roots: ['<rootDir>/src/'],
    setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  },
);
