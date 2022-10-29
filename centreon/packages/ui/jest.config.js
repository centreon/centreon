const baseConfig = require('../frontend-config/jest');

module.exports = {
  ...baseConfig,
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
    '^react($|/.+)': '<rootDir>/../../node_modules/react$1',
  },
  roots: ['<rootDir>/src/'],
  setupFilesAfterEnv: [
    '<rootDir>/setupTests.js',
    '@testing-library/jest-dom/extend-expect',
  ],
};
