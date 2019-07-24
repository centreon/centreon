module.exports = {
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  snapshotSerializers: ['jest-emotion'],
  roots: ['<rootDir>/src/'],
  transform: {
    '^.+\\.jsx?$': 'babel-jest',
  },
  moduleNameMapper: {
    '\\.(s?css)$': 'identity-obj-proxy',
  },
  testPathIgnorePatterns: ['/node_modules/', '/lib/'],
};
