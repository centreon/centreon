const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('../js-config/jest/centreon-ui'), {
  roots: ['<rootDir>/src/'],
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
});
