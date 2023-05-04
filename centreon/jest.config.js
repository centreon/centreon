const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('./packages/js-config/jest'), {
  moduleNameMapper: {
    '\\.(s?css|png|svg|jpg)$': '<rootDir>/www/front_src/src/__mocks__/image.js'
  },
  roots: ['<rootDir>/www/front_src/src/'],
  setupFilesAfterEnv: [
    '@testing-library/jest-dom/extend-expect',
    '<rootDir>/setupTest.js'
  ],
  testEnvironmentOptions: {
    url: 'http://localhost/'
  },
  testMatch: ['**/__tests__/**/*.[jt]s?(x)', '**/?(*.)+(test).[jt]s?(x)'],
  testResultsProcessor: 'jest-junit',
  transformIgnorePatterns: [
    '<rootDir>/node_modules/(?!d3-array|d3-scale|d3-interpolation|d3-interpolate)'
  ]
});
