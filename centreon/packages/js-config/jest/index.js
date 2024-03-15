module.exports = {
  moduleNameMapper: {
    '\\.(s?css|png|svg|jpg)$': 'identity-obj-proxy',
  },
  setupFilesAfterEnv: ['@testing-library/jest-dom/extend-expect'],
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
  transformIgnorePatterns: ['/node_modules/(?!(@centreon)).+\\.jsx?$'],
};
