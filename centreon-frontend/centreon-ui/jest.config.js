module.exports = {
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  roots: ['<rootDir>/src/'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest',
  },
  moduleNameMapper: {
    '\\.(s?css|png|svg)$': 'identity-obj-proxy',
  },
  testPathIgnorePatterns: ['/node_modules/', '/lib/'],
};
