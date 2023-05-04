const baseConfig = require('../js-config/jest');

module.exports = {
  ...baseConfig,
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
    '^react($|/.+)': '<rootDir>/../../node_modules/react$1'
  },
  reporters: ['default', ['jest-junit', { outputName: 'junit.xml' }]],
  roots: ['<rootDir>/src/'],
  setupFilesAfterEnv: [
    '<rootDir>/setupTests.js',
    '@testing-library/jest-dom/extend-expect'
  ],
  testResultsProcessor: 'jest-junit',
  transform: {
    ...baseConfig.transform,
    '^.+\\.mdx?$': '@storybook/addon-docs/jest-transform-mdx'
  },
  transformIgnorePatterns: [
    '<rootDir>/../../node_modules/(?!d3-array|d3-scale|d3-interpolation|d3-interpolate)'
  ]
};
