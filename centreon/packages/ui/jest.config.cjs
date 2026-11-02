const baseConfig = require('../js-config/jest');

module.exports = {
  ...baseConfig,
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
    '^@centreon/ui/fonts(.*)$': '<rootDir>/src/fonts$1',
    '^react($|/.+)': '<rootDir>/../../node_modules/react$1',
    'd3-array': '<rootDir>/node_modules/d3-array/dist/d3-array.min.js'
  },
  reporters: ['default', ['jest-junit', { outputName: 'junit.xml' }]],
  roots: ['<rootDir>/src/', '<rootDir>/test/'],
  setupFilesAfterEnv: ['<rootDir>/test/setupTests.js'],
  testPathIgnorePatterns: [
    ...baseConfig.testPathIgnorePatterns,
    // The browser tests run with rstest (rstest.config.ts), not Jest.
    '\\.browser\\.test\\.[jt]sx?$'
  ],
  testResultsProcessor: 'jest-junit',
  transform: baseConfig.transform,
  transformIgnorePatterns: [
    '<rootDir>/../../node_modules/(?!d3-array|d3-scale|d3-interpolation|d3-interpolate)'
  ]
};
