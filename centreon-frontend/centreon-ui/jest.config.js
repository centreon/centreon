const merge = require('lodash/merge');

module.exports = merge(require('@centreon/frontend-core/jest'), {
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  roots: ['<rootDir>/src/'],
});
