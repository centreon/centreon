module.exports = {
  'setupFilesAfterEnv': ['<rootDir>/setupTests.js'],
  'transform': {
    '^.+\\.jsx?$': 'babel-jest'
  },
  'moduleNameMapper': {
    '\\.(s?css)$': "identity-obj-proxy"
  }
};
