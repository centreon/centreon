const path = require('path');
const os = require('os');

const ReactRefreshWebpackPlugin = require('@pmmmwh/react-refresh-webpack-plugin');
const { merge } = require('webpack-merge');

const {
  getDevConfiguration,
  devJscTransformConfiguration,
  devRefreshJscTransformConfiguration
} = require('./packages/js-config/webpack/patch/dev');
const getBaseConfiguration = require('./webpack.config');

const devServerPort = 9090;

const interfaces = os.networkInterfaces();
const externalInterface = Object.keys(interfaces).find(
  (interfaceName) =>
    !interfaceName.includes('docker') &&
    interfaces[interfaceName][0].family === 'IPv4' &&
    interfaces[interfaceName][0].internal === false &&
    !process.env.IS_STATIC_PORT_FORWARDED
);

const devServerAddress = externalInterface
  ? interfaces[externalInterface][0].address
  : 'localhost';

const publicPath = `http://${devServerAddress}:${devServerPort}/static/`;

const isServeMode = process.env.WEBPACK_ENV === 'serve';
const isDevelopmentMode = process.env.WEBPACK_ENV === 'development';

const plugins = isServeMode ? [new ReactRefreshWebpackPlugin()] : [];

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
      compress: true,
      hot: true,
      port: devServerPort,
      static: [
        {
          directory: `${__dirname}/www/front_src/public`,
          publicPath: '/'
        }
      ]
    },
    output,
    plugins,
    resolve: {
      alias: {
        '@mui/material': path.resolve('./node_modules/@mui/material'),
        dayjs: path.resolve('./node_modules/dayjs'),
        'react-router-dom': path.resolve('./node_modules/react-router-dom')
      }
    }
  }
);
