module.exports = {
  setupFilesAfterEnv: ['@testing-library/jest-dom/extend-expect'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
  transformIgnorePatterns: ['/node_modules/(?!@centreon/ui).+\\.jsx?$'],
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
  },
  testPathIgnorePatterns: ['/node_modules/'],
};