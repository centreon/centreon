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

const getModuleDirectoryPath = (moduleName) =>
  `${__dirname}/www/modules/${moduleName}/static`;

const getWidgetsDirectoryPath = () => `${__dirname}/www/widgets`;

const modules = [
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-license-manager'
  },
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-autodiscovery-server'
  },
  { getDirectoryPath: getModuleDirectoryPath, name: 'centreon-bam-server' },
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-augmented-services'
  },
  {
    getDirectoryPath: () => `${__dirname}/www/modules/centreon-map4-web-client`,
    name: 'centreon-map4-web-client'
  },
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-it-edition-extensions'
  },
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-anomaly-detection'
  },
  {
    getDirectoryPath: getWidgetsDirectoryPath,
    name: ''
  },
  {
    getDirectoryPath: getModuleDirectoryPath,
    name: 'centreon-cloud-extensions'
  }
];

module.exports = merge(getBaseConfiguration(), getDevConfiguration(), {
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
      '@centreon/ui/fonts': path.resolve(
        './node_modules/@centreon/ui/public/fonts'
      ),
      '@mui/material': path.resolve('./node_modules/@mui/material'),
      dayjs: path.resolve('./node_modules/dayjs'),
      'react-router-dom': path.resolve('./node_modules/react-router-dom')
    }
  }
});
