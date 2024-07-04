const path = require('path');

const { merge } = require('webpack-merge');

const { getDevConfiguration } = require('../js-config/rspack/patch/dev');
const getBaseConfiguration = require('../../rspack.config');
const {
  devServer,
  devServerPlugins,
  isDevelopmentMode,
  publicPath
} = require('../js-config/rspack/patch/devServer');

const output = isDevelopmentMode
  ? {
      publicPath
    }
  : {};

module.exports = merge(getBaseConfiguration(true), getDevConfiguration(), {
  devServer: {
    ...devServer,
    port: 9092
  },
  devtool: false,
  output,
  plugins: devServerPlugins
});
