const path = require('path');

const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const { ModuleFederationPlugin } = require('webpack').container;

const {
  getModuleConfiguration,
  optimization,
  output,
  cache
} = require('./globalConfig');

const getBaseConfiguration = ({
  moduleName,
  moduleFederationConfig,
  jscTransformConfiguration
}) => ({
  cache,
  module: getModuleConfiguration(jscTransformConfiguration),
  optimization,
  output,
  plugins: [
    new CleanWebpackPlugin(),
    moduleName &&
      new ModuleFederationPlugin({
        filename: 'remoteEntry.[chunkhash:8].js',
        library: { name: moduleName, type: 'var' },
        name: moduleName,
        shared: [
          {
            '@centreon/ui-context': {
              requiredVersion: '22.10.0',
              singleton: true
            }
          },
          {
            jotai: {
              requiredVersion: '1.x',
              singleton: true
            }
          },
          {
            'jotai-suspense': {
              requiredVersion: '0.1.x',
              singleton: true
            }
          },
          {
            react: {
              requiredVersion: '18.x',
              singleton: true
            }
          },
          {
            'react-dom': {
              requiredVersion: '18.x',
              singleton: true
            }
          },
          {
            'react-i18next': {
              requiredVersion: '11.x',
              singleton: true
            }
          },
          {
            'react-router-dom': {
              requiredVersion: '6.x',
              singleton: true
            }
          }
        ],
        ...moduleFederationConfig
      })
  ].filter(Boolean),
  resolve: {
    alias: {
      react: path.resolve('./node_modules/react')
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx']
  }
});

module.exports = getBaseConfiguration;
