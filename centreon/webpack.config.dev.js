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

const getStaticDirectoryPath = (moduleName) =>
  `${__dirname}/www/modules/${moduleName}/static`;

const modules = [
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-license-manager'
  },
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-autodiscovery-server'
  },
  { getDirectoryPath: getStaticDirectoryPath, name: 'centreon-bam-server' },
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-augmented-services'
  },
  {
    getDirectoryPath: () => `${__dirname}/www/modules/centreon-map4-web-client`,
    name: 'centreon-map4-web-client'
  },
  {
    getDirectoryPath: getStaticDirectoryPath,
    name: 'centreon-it-edition-extensions'
  }
];

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
      headers: { 'Access-Control-Allow-Origin': '*' },
      host: '0.0.0.0',

      static: modules.map(({ name, getDirectoryPath }) => ({
        directory: path.resolve(getDirectoryPath(name)),
        publicPath,
        watch: true
      }))
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
