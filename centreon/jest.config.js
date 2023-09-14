const { mergeDeepRight } = require('ramda');

module.exports = mergeDeepRight(require('./packages/js-config/jest'), {
  moduleNameMapper: {
    '\\.(s?css|png|svg|jpg)$': '<rootDir>/www/front_src/src/__mocks__/image.js',
    'd3-array': '<rootDir>/node_modules/d3-array/dist/d3-array.min.js',
    'd3-color': '<rootDir>/node_modules/d3-color/dist/d3-color.min.js',
    'd3-format': '<rootDir>/node_modules/d3-format/dist/d3-format.min.js',
    'd3-interpolate':
      '<rootDir>/node_modules/d3-interpolate/dist/d3-interpolate.min.js',
    'd3-scale': '<rootDir>/node_modules/d3-scale/dist/d3-scale.min.js',
    'd3-time': '<rootDir>/node_modules/d3-time/dist/d3-time.min.js'
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
  testResultsProcessor: 'jest-junit'
});
