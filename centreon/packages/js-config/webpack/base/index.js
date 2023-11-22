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
  jscTransformConfiguration,
  enableCoverage
}) => ({
  cache,
  module: getModuleConfiguration(jscTransformConfiguration, enableCoverage),
  optimization,
  output: {
    ...output,
    library: moduleName,
    uniqueName: moduleName
  },
  plugins: [
    new CleanWebpackPlugin(),
    moduleName &&
      new ModuleFederationPlugin({
        filename: 'remoteEntry.[chunkhash:8].js',
        library: { name: moduleName, type: 'umd' },
        name: moduleName,
        shared: [
          {
            '@centreon/ui-context': {
              requiredVersion: '24.x',
              singleton: true
            }
          },
          {
            jotai: {
              requiredVersion: '2.x',
              singleton: true
            }
          },
          {
            'jotai-suspense': {
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
      '@centreon/ui/fonts': path.resolve(
        './node_modules/@centreon/ui/public/fonts'
      ),
      react: path.resolve('./node_modules/react')
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx']
  }
});

module.exports = getBaseConfiguration;
