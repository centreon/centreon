const merge = require('webpack-merge');

const webpackConfig = require('.');

module.exports = merge(webpackConfig, {
  devtool: 'inline-source-map',
  output: {
    filename: '[name].js',
  },
});
