module.exports = {
  setupFilesAfterEnv: ['<rootDir>/setupTests.js'],
  snapshotSerializers: ['jest-emotion'],
  transform: {
    '^.+\\.jsx?$': 'babel-jest',
  },
  moduleNameMapper: {
    '\\.(s?css)$': 'identity-obj-proxy',
  },
};
