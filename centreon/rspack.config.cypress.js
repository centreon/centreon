const path = require('path');

const { merge } = require('webpack-merge');

const {
  getDevConfiguration
} = require('./packages/js-config/rspack/patch/dev');
const getBaseConfiguration = require('./rspack.config');
const {
  devServer,
  devServerPlugins,
  isDevelopmentMode,
  publicPath
} = require('./packages/js-config/rspack/patch/devServer');

const output = isDevelopmentMode
  ? {
      publicPath
    }
  : {};

module.exports = merge(getBaseConfiguration(true), getDevConfiguration(), {
  devServer: {
    ...devServer,
    port: 9092,
    static: [
      {
        directory: `${__dirname}/www/front_src/public`,
        publicPath: '/'
      }
    ]
  },
  devtool: false,
  output,
  plugins: devServerPlugins,
  resolve: {
    alias: {
      '@centreon/ui/fonts': path.resolve(
        './node_modules/@centreon/ui/public/fonts'
      ),
      '@mui/material': path.resolve('./node_modules/@mui/material'),
      'centreon-widgets': path.resolve('www', 'widgets', 'src'),
      dayjs: path.resolve('./node_modules/dayjs'),
      'react-router-dom': path.resolve('./node_modules/react-router-dom')
    }
  }
});
