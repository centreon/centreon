const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('../js-config/jest/centreon-ui'), {
  moduleNameMapper: {
    '^axios$': require.resolve('axios'),
  },
  roots: ['<rootDir>/src/'],
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
});
