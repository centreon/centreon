const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(
<<<<<<< HEAD
  require('centreon-frontend/packages/frontend-config/jest'),
  {
    moduleNameMapper: {
      'd3-array': '<rootDir>/node_modules/d3-array/dist/d3-array.min.js',
    },
=======
  require('@centreon/centreon-frontend/packages/frontend-config/jest'),
  {
>>>>>>> centreon/dev-21.10.x
    roots: ['<rootDir>/www/front_src/src/'],
    setupFilesAfterEnv: [
      '@testing-library/jest-dom/extend-expect',
      '<rootDir>/setupTest.js',
    ],
    testEnvironment: 'jsdom',
  },
);
