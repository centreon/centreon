const baseConfig = require('../js-config/jest');

module.exports = {
  ...baseConfig,
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
    '^react($|/.+)': '<rootDir>/../../node_modules/react$1',
    '^@centreon/ui/fonts(.*)$': '<rootDir>/public/fonts$1'
  },
  reporters: ['default', ['jest-junit', { outputName: 'junit.xml' }]],
  roots: ['<rootDir>/src/', '<rootDir>/test/'],
  setupFilesAfterEnv: [
    '<rootDir>/test/setupTests.js',
    '@testing-library/jest-dom/extend-expect'
  ],
  testResultsProcessor: 'jest-junit',
  transform: {
    ...baseConfig.transform,
    '^.+\\.mdx?$': '@storybook/addon-docs/jest-transform-mdx'
  }
};
