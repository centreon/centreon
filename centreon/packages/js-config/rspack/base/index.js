const path = require('path');

const rspack = require('@rspack/core');

const {
  getModuleConfiguration,
  optimization,
  output,
  cache
} = require('./globalConfig');

const getBaseConfiguration = ({
  moduleName,
  moduleFederationConfig,
  enableCoverage,
  postCssBase
}) => ({
  cache,
  module: {
    ...getModuleConfiguration(enableCoverage, postCssBase),
    parser: {
      'css/auto': {
        namedExports: false
      },
      'css/module': {
        namedExports: false
      }
    },
    generator: {
      'css/auto': {
        exportsOnly: false
      },
      'css/module': {
        exportsOnly: false
      }
    }
  },
  optimization,
  output: {
    ...output,
    clean: true,
    library: moduleName,
    uniqueName: moduleName
  },
  plugins: [
    moduleName &&
    new rspack.container.ModuleFederationPlugin({
      filename: 'remoteEntry.[chunkhash:8].js',
      injectTreeShakingUsedExports: false,
      library: { name: moduleName, type: 'umd' },
      name: moduleName,
      shared: [
        {
          '@centreon/ui-context': {
            requiredVersion: '1.x',
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
            requiredVersion: '19.x',
            singleton: true
          }
        },
        {
          'react-i18next': {
            requiredVersion: '15.x',
            singleton: true
          }
        },
        {
          'react-router': {
            requiredVersion: '7.x',
            singleton: true
          }
        },
        { tailwindcss: { singleton: true, requiredVersion: '4.x' } }
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
