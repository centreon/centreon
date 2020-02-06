const webpackConfig = require('.');

webpackConfig.module.rules = [
  ...webpackConfig.module.rules,
  {
    test: /\.tsx?$/,
    exclude: /node_modules/,
    use: ['babel-loader', 'awesome-typescript-loader'],
  }
];
webpackConfig.resolve.extensions = ['.js', '.jsx', '.ts', '.tsx'],

module.exports = webpackConfig;