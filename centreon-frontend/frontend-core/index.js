module.exports = {
  jest: require('./jest'),
  webpack: require('./webpack'),
  webpackDev: require('./webpack/devConfig'),
  webpackCommonVendor: require('./webpack/commonVendor'),
  webpackTypeScript: require('./webpack/typescript'),
  eslint: require('./eslint'),
  eslintTypeScript: require('./eslint/typescript'),
  babel: require('./babel'),
  babelTypeScript: require('./babel/typescript'),
}