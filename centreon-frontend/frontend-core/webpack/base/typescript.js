const merge = require('webpack-merge');

const webpackConfig = require('.');

module.exports = merge(webpackConfig, {
  resolve: {
    extensions: ['.ts', '.tsx'],
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        exclude: /node_modules(?!(\\|\/)@centreon(\\|\/)ui)/,
        use: ['babel-loader', 'awesome-typescript-loader'],
      },
    ],
  },
});
