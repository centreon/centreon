const path = require('path');

const { merge } = require('webpack-merge');

const {
  getDevConfiguration,
  devJscTransformConfiguration,
  devRefreshJscTransformConfiguration
} = require('./packages/js-config/webpack/patch/dev');
const getBaseConfiguration = require('./webpack.config');
const {
  devServer,
  devServerPlugins,
  isServeMode,
  isDevelopmentMode,
  publicPath
} = require('./packages/js-config/webpack/patch/devServer');

const output =
  isServeMode || isDevelopmentMode
    ? {
        publicPath
      }
    : {};

module.exports = merge(
  getBaseConfiguration(
    isServeMode
      ? devRefreshJscTransformConfiguration
      : devJscTransformConfiguration
  ),
  getDevConfiguration(),
  {
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
    output,
    plugins: devServerPlugins,
    resolve: {
      alias: {
        '@mui/material': path.resolve('./node_modules/@mui/material'),
        dayjs: path.resolve('./node_modules/dayjs'),
        'react-router-dom': path.resolve('./node_modules/react-router-dom')
      }
    }
  }
);
