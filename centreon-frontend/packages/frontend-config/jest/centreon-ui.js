module.exports = {
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
  },
  setupFilesAfterEnv: ['@testing-library/jest-dom/extend-expect'],
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
};
