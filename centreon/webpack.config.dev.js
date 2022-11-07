const path = require('path');
const os = require('os');

const ReactRefreshWebpackPlugin = require('@pmmmwh/react-refresh-webpack-plugin');
const { merge } = require('webpack-merge');
<<<<<<< HEAD
const devConfig = require('centreon-frontend/packages/frontend-config/webpack/patch/dev');
=======

const devConfig = require('@centreon/centreon-frontend/packages/frontend-config/webpack/patch/dev');
>>>>>>> centreon/dev-21.10.x

const baseConfig = require('./webpack.config');

const devServerPort = 9090;

const interfaces = os.networkInterfaces();
const externalInterface = Object.keys(interfaces).find(
  (interfaceName) =>
    !interfaceName.includes('docker') &&
    interfaces[interfaceName][0].family === 'IPv4' &&
<<<<<<< HEAD
    interfaces[interfaceName][0].internal === false &&
    !process.env.IS_STATIC_PORT_FORWARDED,
=======
    interfaces[interfaceName][0].internal === false,
>>>>>>> centreon/dev-21.10.x
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
        publicPath,
      }
    : {};

<<<<<<< HEAD
const getStaticDirectoryPath = (moduleName) =>
  `${__dirname}/www/modules/${moduleName}/static`;

const modules = [
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-license-manager',
  },
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-autodiscovery-server',
  },
  { getDirectoryPath: getStaticDirectoryPath, name: 'centreon-bam-server' },
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-augmented-services',
  },
  {
    getDirectoryPath: () => `${__dirname}/www/modules/centreon-map4-web-client`,
    name: 'centreon-map4-web-client',
  },
=======
const modules = [
  'centreon-license-manager',
  'centreon-autodiscovery-server',
  'centreon-bam-server',
  'centreon-augmented-services',
];

const modules = [
  'centreon-license-manager',
  'centreon-autodiscovery-server',
  'centreon-bam-server',
  'centreon-augmented-services',
>>>>>>> centreon/dev-21.10.x
];

module.exports = merge(baseConfig, devConfig, {
  devServer: {
    compress: true,
    headers: { 'Access-Control-Allow-Origin': '*' },
    host: '0.0.0.0',
    hot: true,
    port: devServerPort,
<<<<<<< HEAD
    static: modules.map(({ name, getDirectoryPath }) => ({
      directory: path.resolve(getDirectoryPath(name)),
=======

    static: modules.map((module) => ({
      directory: path.resolve(`${__dirname}/www/modules/${module}/static`),
>>>>>>> centreon/dev-21.10.x
      publicPath,
      watch: true,
    })),
  },
  output,
  plugins,
  resolve: {
    alias: {
<<<<<<< HEAD
      '@mui/material': path.resolve('./node_modules/@mui/material'),
=======
      '@material-ui/core': path.resolve('./node_modules/@material-ui/core'),
>>>>>>> centreon/dev-21.10.x
      dayjs: path.resolve('./node_modules/dayjs'),
      'react-router-dom': path.resolve('./node_modules/react-router-dom'),
    },
  },
});
