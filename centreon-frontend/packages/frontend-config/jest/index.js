module.exports = {
  setupFilesAfterEnv: ['@testing-library/jest-dom/extend-expect'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
  transformIgnorePatterns: ['/node_modules/(?!@centreon/ui).+\\.jsx?$'],
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
    '@centreon/ui': '<rootDir>/node_modules/@centreon/centreon-frontend/packages/centreon-ui',
    '@centreon/ui-context': '<rootDir>/node_modules/@centreon/centreon-frontend/packages/ui-context'
  },
  testPathIgnorePatterns: ['/node_modules/'],
};